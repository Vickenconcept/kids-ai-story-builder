<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\FeatureTier;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class BundleRegisterController extends Controller
{
    private const SESSION_TIER_KEY = 'bundle_register.tier';

    public function create(Request $request): Response|RedirectResponse
    {
        if (! Features::enabled(Features::registration())) {
            abort(404);
        }

        $tier = $this->resolveTierFromAccess($request->query('access'));

        if (! $tier instanceof FeatureTier) {
            return redirect()->route('bundle.register', ['access' => FeatureTier::Basic->value]);
        }

        $request->session()->put(self::SESSION_TIER_KEY, $tier->value);

        return Inertia::render('auth/BundleRegister', [
            'access' => $tier->value,
            'accessLabel' => ucfirst($tier->value),
        ]);
    }

    public function store(Request $request, CreateNewUser $creator): RedirectResponse
    {
        if (! Features::enabled(Features::registration())) {
            abort(404);
        }

        $tier = $this->resolveTierFromSession($request);

        if (! $tier instanceof FeatureTier) {
            return redirect()
                ->route('bundle.register', ['access' => FeatureTier::Basic->value])
                ->withErrors(['email' => 'Your registration link has expired. Please open your bundle link again.']);
        }

        $user = $creator->create($request->only([
            'name',
            'email',
            'password',
            'password_confirmation',
        ]));

        $creditsByTier = config('jvzoo.tier_credits', []);
        $credits = (int) ($creditsByTier[$tier->value] ?? 30);

        $user->forceFill([
            'feature_tier' => $tier,
            'story_credits' => max(0, $credits),
            'email_verified_at' => now(),
        ])->save();

        $request->session()->forget(self::SESSION_TIER_KEY);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(config('fortify.home'));
    }

    private function resolveTierFromAccess(mixed $access): ?FeatureTier
    {
        if (! is_string($access) || $access === '') {
            return null;
        }

        return FeatureTier::tryFrom(strtolower($access));
    }

    private function resolveTierFromSession(Request $request): ?FeatureTier
    {
        $value = $request->session()->get(self::SESSION_TIER_KEY);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return FeatureTier::tryFrom($value);
    }

}
