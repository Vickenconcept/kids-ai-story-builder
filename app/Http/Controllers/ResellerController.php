<?php

namespace App\Http\Controllers;

use App\Enums\FeatureTier;
use App\Mail\UserNotificationMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    public function index(Request $request): Response
    {
        $reseller = $request->user();

        $accounts = $reseller->resellerSubAccounts()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'story_credits', 'created_at']);

        return Inertia::render('Reseller/Index', [
            'accounts' => $accounts,
            'maxSubAccounts' => config('reseller.max_sub_accounts_per_elite'),
            'subAccountCredits' => config('reseller.sub_account_credits'),
            'currentCount' => $accounts->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $reseller = $request->user();
        $max = (int) config('reseller.max_sub_accounts_per_elite');

        if ($reseller->resellerSubAccounts()->count() >= $max) {
            return back()->withErrors([
                'email' => "You can create up to {$max} sub-accounts.",
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ]);

        $plainPassword = Str::password(16);

        $tier = FeatureTier::tryFrom((string) config('reseller.sub_account_tier'))
            ?? FeatureTier::Basic;

        $subUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $plainPassword,
            'story_credits' => (int) config('reseller.sub_account_credits'),
            'feature_tier' => $tier,
            'reseller_id' => $reseller->id,
            'email_verified_at' => now(),
        ]);

        $appName = (string) config('app.name', 'DreamForge AI');
        $loginUrl = url('/login');

        Mail::to($subUser->email)->send(new UserNotificationMail(
            subjectLine: "Your {$appName} account is ready",
            headline: "Welcome, {$subUser->name}",
            lines: [
                "{$reseller->name} created a {$appName} account for you.",
                'Sign in with this email address and the temporary password below.',
                "Temporary password: {$plainPassword}",
                'Change your password after you sign in under Settings.',
            ],
            ctaLabel: 'Sign in',
            ctaUrl: $loginUrl,
        ));

        return back()->with('success', 'Account created and an invitation email was sent.');
    }
}
