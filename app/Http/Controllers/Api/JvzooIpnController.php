<?php

namespace App\Http\Controllers\Api;

use App\Enums\FeatureTier;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JvzooIpnController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        if (! $this->isValidCverify($payload)) {
            Log::warning('JVZoo IPN rejected: invalid cverify.', [
                'transaction_id' => $payload['transaction_id'] ?? null,
                'paykey' => $payload['paykey'] ?? null,
                'product_id' => $payload['product_id'] ?? null,
                'email' => $payload['customer_email'] ?? null,
            ]);

            return response('INVALID CVERIFY', 400);
        }

        $transactionType = strtoupper((string) ($payload['transaction_type'] ?? ''));
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (! in_array($transactionType, ['SALE', 'BILL', 'REBILL', 'RFND', 'CGBK'], true)) {
            return response('IGNORED', 200);
        }

        if ($transactionType === 'SALE' && $status !== 'COMPLETED') {
            return response('IGNORED', 200);
        }

        $email = (string) ($payload['customer_email'] ?? '');
        $productId = (string) ($payload['product_id'] ?? '');

        if ($email === '' || $productId === '') {
            Log::warning('JVZoo IPN ignored: missing required fields.', [
                'transaction_type' => $transactionType,
                'status' => $status,
                'payload_keys' => array_keys($payload),
            ]);

            return response('MISSING REQUIRED FIELDS', 200);
        }

        if (in_array($transactionType, ['SALE', 'BILL', 'REBILL'], true)) {
            $this->createOrUpgradeUser($payload);

            return response('OK', 200);
        }

        if (in_array($transactionType, ['RFND', 'CGBK'], true)) {
            $this->downgradeUserOnReversal($payload);
        }

        return response('OK', 200);
    }

    private function createOrUpgradeUser(array $payload): void
    {
        $email = strtolower((string) $payload['customer_email']);
        $productId = (string) $payload['product_id'];
        $firstName = trim((string) ($payload['customer_first_name'] ?? ''));
        $lastName = trim((string) ($payload['customer_last_name'] ?? ''));

        $tier = $this->tierForProductId($productId);

        if (! $tier instanceof FeatureTier) {
            Log::warning('JVZoo IPN sale received with unmapped product_id.', [
                'product_id' => $productId,
                'email' => $email,
                'transaction_id' => $payload['transaction_id'] ?? null,
            ]);

            return;
        }

        $name = trim($firstName.' '.$lastName);
        if ($name === '') {
            $name = Str::before($email, '@');
        }

        $creditsByTier = config('jvzoo.tier_credits', []);
        $targetCredits = (int) ($creditsByTier[$tier->value] ?? 30);

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make(Str::random(16)),
                'feature_tier' => $tier,
                'story_credits' => max(0, $targetCredits),
            ]);

            Log::info('JVZoo IPN: user created.', [
                'user_id' => $user->id,
                'email' => $email,
                'tier' => $tier->value,
                'product_id' => $productId,
            ]);

            return;
        }

        $currentTier = $user->feature_tier instanceof FeatureTier
            ? $user->feature_tier
            : FeatureTier::Basic;

        if ($this->tierRank($tier) > $this->tierRank($currentTier)) {
            $user->feature_tier = $tier;
        }

        // Ensure credits are at least the floor for the resulting tier.
        $finalTier = $user->feature_tier instanceof FeatureTier ? $user->feature_tier : $tier;
        $finalCreditsFloor = (int) ($creditsByTier[$finalTier->value] ?? 30);
        $user->story_credits = max((int) $user->story_credits, $finalCreditsFloor);
        $user->save();

        Log::info('JVZoo IPN: user upgraded/synced.', [
            'user_id' => $user->id,
            'email' => $email,
            'tier' => $finalTier->value,
            'product_id' => $productId,
        ]);
    }

    private function downgradeUserOnReversal(array $payload): void
    {
        $email = strtolower((string) ($payload['customer_email'] ?? ''));
        if ($email === '') {
            return;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return;
        }

        // Conservative default: move account to Basic on refund/chargeback event.
        $user->feature_tier = FeatureTier::Basic;
        $user->story_credits = min((int) $user->story_credits, (int) (config('jvzoo.tier_credits.basic', 30)));
        $user->save();

        Log::info('JVZoo IPN: user downgraded after reversal.', [
            'user_id' => $user->id,
            'email' => $email,
            'transaction_type' => strtoupper((string) ($payload['transaction_type'] ?? '')),
            'transaction_id' => $payload['transaction_id'] ?? null,
        ]);
    }

    private function tierForProductId(string $productId): ?FeatureTier
    {
        $map = config('jvzoo.product_tiers', []);
        $tierValue = $map[$productId] ?? null;

        if (! is_string($tierValue)) {
            return null;
        }

        return match (strtolower($tierValue)) {
            'basic' => FeatureTier::Basic,
            'pro' => FeatureTier::Pro,
            'elite' => FeatureTier::Elite,
            default => null,
        };
    }

    private function tierRank(FeatureTier $tier): int
    {
        return match ($tier) {
            FeatureTier::Basic => 1,
            FeatureTier::Pro => 2,
            FeatureTier::Elite => 3,
        };
    }

    private function isValidCverify(array $payload): bool
    {
        $incoming = strtoupper((string) ($payload['cverify'] ?? ''));
        $secret = (string) config('jvzoo.secret', '');

        if ($incoming === '' || $secret === '') {
            return false;
        }

        $paykey = (string) ($payload['paykey'] ?? '');
        $customerEmail = (string) ($payload['customer_email'] ?? '');
        $productName = (string) ($payload['product_name'] ?? '');
        $transactionType = (string) ($payload['transaction_type'] ?? '');
        $date = (string) ($payload['date'] ?? '');

        $signatureBase = $paykey
            .'|'.$customerEmail
            .'|'.$productName
            .'|'.$transactionType
            .'|'.$date
            .$secret;

        $calculated = sha1(mb_convert_encoding($signatureBase, 'UTF-8'));
        $calculated = strtoupper(substr($calculated, 0, 8));

        return hash_equals($calculated, $incoming);
    }
}
