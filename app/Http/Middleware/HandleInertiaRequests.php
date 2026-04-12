<?php

namespace App\Http\Middleware;

use App\Enums\FeatureTier;
use App\Models\CreditPack;
use App\Models\StoryPlan;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'canManageCreditPacks' => $user?->can('manage-credit-packs') ?? false,
                'canManagePlans' => $user?->can('manage-plans') ?? false,
                'canManageUsers' => $user?->can('manage-users') ?? false,
                'canUseReseller' => $user?->feature_tier === FeatureTier::Elite,
            ],
            'jvzoo' => [
                'eliteProductId' => (string) config('jvzoo.elite_product_id', ''),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                /** One-time sign-in details when reseller invitation email could not be sent. */
                'reseller_invite_fallback' => fn () => $request->session()->get('reseller_invite_fallback'),
            ],
            'billing' => [
                'paypalClientId' => config('services.paypal.client_id'),
                'creditPacks' => $user
                    ? CreditPack::query()
                        ->active()
                        ->ordered()
                        ->get([
                            'id',
                            'name',
                            'description',
                            'credits',
                            'price_cents',
                            'currency',
                        ])
                    : [],
                'storyPlans' => $user
                    ? StoryPlan::query()
                        ->active()
                        ->ordered()
                        ->get([
                            'id',
                            'name',
                            'description',
                            'tier',
                            'included_credits',
                            'price_cents',
                            'currency',
                            'is_featured',
                            'feature_list',
                        ])
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
