<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\CreditPurchase;
use App\Models\PayPalCheckoutIntent;
use App\Services\Billing\PayPalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CreditPurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Credits/Index', [
            'packs' => CreditPack::query()
                ->active()
                ->ordered()
                ->get([
                    'id',
                    'name',
                    'description',
                    'credits',
                    'price_cents',
                    'currency',
                ]),
            'purchases' => CreditPurchase::query()
                ->where('user_id', $user->id)
                ->latest('purchased_at')
                ->limit(20)
                ->get([
                    'id',
                    'pack_name',
                    'credits_awarded',
                    'amount_cents',
                    'currency',
                    'status',
                    'purchased_at',
                ]),
            'storyCredits' => (int) $user->story_credits,
        ]);
    }

    public function createPayPalOrder(Request $request, PayPalClient $payPal): JsonResponse
    {
        $validated = $request->validate([
            'pack_id' => ['required', 'integer', 'exists:credit_packs,id'],
        ]);

        $pack = CreditPack::query()->active()->findOrFail($validated['pack_id']);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $pack->id,
                'description' => sprintf('%s (%d credits)', $pack->name, $pack->credits),
                'amount' => [
                    'currency_code' => $pack->currency,
                    'value' => number_format($pack->price_cents / 100, 2, '.', ''),
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
                'domain' => 'credits',
                'target_id' => $pack->id,
                'amount_cents' => (int) $pack->price_cents,
                'currency' => strtoupper($pack->currency),
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
            'pack_id' => ['required', 'integer', 'exists:credit_packs,id'],
            'order_id' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $pack = CreditPack::query()->active()->findOrFail($validated['pack_id']);

        $intent = PayPalCheckoutIntent::query()
            ->where('provider_order_id', $validated['order_id'])
            ->where('user_id', $user->id)
            ->where('domain', 'credits')
            ->where('target_id', $pack->id)
            ->first();

        if (! $intent || $intent->consumed_at !== null || $intent->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'paypal' => 'Invalid or expired checkout intent. Please start checkout again.',
            ]);
        }

        if ((int) $intent->amount_cents !== (int) $pack->price_cents || strtoupper($intent->currency) !== strtoupper($pack->currency)) {
            throw ValidationException::withMessages([
                'paypal' => 'Checkout intent does not match selected pack.',
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

        if ($amountCents !== (int) $pack->price_cents || $currency !== strtoupper($pack->currency)) {
            throw ValidationException::withMessages([
                'paypal' => 'Captured amount does not match selected pack.',
            ]);
        }

        if ($captureId === '') {
            throw ValidationException::withMessages([
                'paypal' => 'Missing PayPal capture id.',
            ]);
        }

        $credited = DB::transaction(function () use ($user, $pack, $validated, $capture, $captureId): bool {
            $intent = PayPalCheckoutIntent::query()
                ->where('provider_order_id', $validated['order_id'])
                ->lockForUpdate()
                ->first();

            if (! $intent || $intent->consumed_at !== null) {
                return false;
            }

            $existing = CreditPurchase::query()
                ->where('provider_order_id', $validated['order_id'])
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'completed') {
                return false;
            }

            $purchase = $existing ?? new CreditPurchase([
                'provider_order_id' => $validated['order_id'],
            ]);

            $wasCompleted = $purchase->status === 'completed';

            $purchase->fill([
                'user_id' => $user->id,
                'credit_pack_id' => $pack->id,
                'pack_name' => $pack->name,
                'credits_awarded' => $pack->credits,
                'amount_cents' => $pack->price_cents,
                'currency' => strtoupper($pack->currency),
                'provider' => 'paypal',
                'provider_capture_id' => $captureId,
                'status' => 'completed',
                'raw_payload' => $capture,
                'purchased_at' => now(),
            ]);
            $purchase->save();

            if (! $wasCompleted) {
                $user->increment('story_credits', $pack->credits);
                $intent->update(['consumed_at' => now()]);

                return true;
            }

            return false;
        });

        return response()->json([
            'credited' => $credited,
            'credits_added' => $credited ? $pack->credits : 0,
            'message' => $credited
                ? sprintf('%d credits added to your account.', $pack->credits)
                : 'Payment already processed for this order.',
        ]);
    }
}
