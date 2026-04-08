<?php

namespace App\Http\Controllers\Billing;

use App\Enums\FeatureTier;
use App\Http\Controllers\Controller;
use App\Mail\UserNotificationMail;
use App\Models\PayPalCheckoutIntent;
use App\Models\PlanPurchase;
use App\Models\StoryPlan;
use App\Services\Billing\PayPalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanUpgradeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Plans/Index', [
            'plans' => StoryPlan::query()
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
                ]),
            'currentTier' => $user->feature_tier?->value ?? FeatureTier::Basic->value,
            'storyCredits' => (int) $user->story_credits,
            'purchases' => PlanPurchase::query()
                ->where('user_id', $user->id)
                ->latest('purchased_at')
                ->limit(20)
                ->get([
                    'id',
                    'plan_name',
                    'tier',
                    'credits_floor',
                    'amount_cents',
                    'currency',
                    'status',
                    'purchased_at',
                ]),
        ]);
    }

    public function createPayPalOrder(Request $request, PayPalClient $payPal): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:story_plans,id'],
        ]);

        $user = $request->user();
        $plan = StoryPlan::query()->active()->findOrFail($validated['plan_id']);

        $this->ensureUpgradeAllowed($user->feature_tier ?? FeatureTier::Basic, $plan->tier);

        if ((int) $plan->price_cents <= 0) {
            throw ValidationException::withMessages([
                'plan' => 'Free plan does not require checkout.',
            ]);
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $plan->id,
                'description' => sprintf('%s plan upgrade', $plan->name),
                'amount' => [
                    'currency_code' => $plan->currency,
                    'value' => number_format($plan->price_cents / 100, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'brand_name' => config('app.name'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $order = $payPal->createOrder($payload);
        $orderId = (string) Arr::get($order, 'id', '');

        if ($orderId === '') {
            throw ValidationException::withMessages([
                'paypal' => 'Unable to create PayPal order.',
            ]);
        }

        PayPalCheckoutIntent::query()->updateOrCreate(
            ['provider_order_id' => $orderId],
            [
                'user_id' => $request->user()->id,
                'domain' => 'plans',
                'target_id' => $plan->id,
                'amount_cents' => (int) $plan->price_cents,
                'currency' => strtoupper($plan->currency),
                'expires_at' => now()->addMinutes(60),
                'consumed_at' => null,
            ]
        );

        return response()->json([
            'id' => $orderId,
        ]);
    }

    public function capturePayPalOrder(Request $request, PayPalClient $payPal): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:story_plans,id'],
            'order_id' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $plan = StoryPlan::query()->active()->findOrFail($validated['plan_id']);

        $intent = PayPalCheckoutIntent::query()
            ->where('provider_order_id', $validated['order_id'])
            ->where('user_id', $user->id)
            ->where('domain', 'plans')
            ->where('target_id', $plan->id)
            ->first();

        if (! $intent || $intent->consumed_at !== null || $intent->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'paypal' => 'Invalid or expired checkout intent. Please start checkout again.',
            ]);
        }

        if ((int) $intent->amount_cents !== (int) $plan->price_cents || strtoupper($intent->currency) !== strtoupper($plan->currency)) {
            throw ValidationException::withMessages([
                'paypal' => 'Checkout intent does not match selected plan.',
            ]);
        }

        $this->ensureUpgradeAllowed($user->feature_tier ?? FeatureTier::Basic, $plan->tier);

        if ((int) $plan->price_cents <= 0) {
            throw ValidationException::withMessages([
                'plan' => 'Free plan does not require checkout.',
            ]);
        }

        $capture = $payPal->captureOrder($validated['order_id']);

        $captureStatus = strtoupper((string) Arr::get($capture, 'status', ''));
        if ($captureStatus !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'paypal' => 'PayPal capture was not completed.',
            ]);
        }

        $amountCents = $payPal->extractCaptureAmountCents($capture);
        $currency = $payPal->extractCurrency($capture);
        $captureId = $payPal->extractCaptureId($capture);

        if ($amountCents !== (int) $plan->price_cents || $currency !== strtoupper($plan->currency)) {
            throw ValidationException::withMessages([
                'paypal' => 'Captured amount does not match selected plan.',
            ]);
        }

        if ($captureId === '') {
            throw ValidationException::withMessages([
                'paypal' => 'Missing PayPal capture id.',
            ]);
        }

        $upgraded = DB::transaction(function () use ($user, $plan, $validated, $capture, $captureId): bool {
            $intent = PayPalCheckoutIntent::query()
                ->where('provider_order_id', $validated['order_id'])
                ->lockForUpdate()
                ->first();

            if (! $intent || $intent->consumed_at !== null) {
                return false;
            }

            $existing = PlanPurchase::query()
                ->where('provider_order_id', $validated['order_id'])
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'completed') {
                return false;
            }

            $purchase = $existing ?? new PlanPurchase([
                'provider_order_id' => $validated['order_id'],
            ]);

            $wasCompleted = $purchase->status === 'completed';

            $purchase->fill([
                'user_id' => $user->id,
                'story_plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'tier' => $plan->tier,
                'credits_floor' => $plan->included_credits,
                'amount_cents' => $plan->price_cents,
                'currency' => strtoupper($plan->currency),
                'provider' => 'paypal',
                'provider_capture_id' => $captureId,
                'status' => 'completed',
                'raw_payload' => $capture,
                'purchased_at' => now(),
            ]);
            $purchase->save();

            if (! $wasCompleted) {
                // Preserve remaining credits and top up to plan floor if needed.
                $user->feature_tier = $plan->tier;
                $user->story_credits = max((int) $user->story_credits, (int) $plan->included_credits);
                $user->save();
                $intent->update(['consumed_at' => now()]);

                return true;
            }

            return false;
        });

        if ($upgraded) {
            try {
                Mail::to($user->email)->send(
                    new UserNotificationMail(
                        subjectLine: 'Plan upgrade successful',
                        headline: 'Your plan has been upgraded',
                        lines: [
                            sprintf('You are now on the %s plan.', $plan->name),
                            sprintf('Your account credits are now at least %d.', (int) $plan->included_credits),
                        ],
                        ctaLabel: 'Open Dashboard',
                        ctaUrl: url('/dashboard'),
                    )
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to send plan upgrade email.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'upgraded' => $upgraded,
            'message' => $upgraded
                ? sprintf(
                    'Plan upgraded to %s. Your credits are now at least %d.',
                    $plan->name,
                    $plan->included_credits
                )
                : 'Payment already processed for this order.',
        ]);
    }

    private function ensureUpgradeAllowed(FeatureTier $currentTier, FeatureTier $targetTier): void
    {
        if ($this->tierRank($targetTier) <= $this->tierRank($currentTier)) {
            throw ValidationException::withMessages([
                'plan' => 'Please select a higher plan to upgrade.',
            ]);
        }
    }

    private function tierRank(FeatureTier $tier): int
    {
        return match ($tier) {
            FeatureTier::Basic => 1,
            FeatureTier::Pro => 2,
            FeatureTier::Elite => 3,
        };
    }
}
