<?php

namespace App\Http\Controllers\Api;

use App\Enums\FeatureTier;
use App\Http\Controllers\Controller;
use App\Jobs\Email\SendCeoOnboardingEmailJob;
use App\Mail\UserNotificationMail;
use App\Models\JvzooIpnEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class JvzooIpnController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        if (! $this->isFreshEvent($payload)) {
            Log::warning('JVZoo IPN ignored: stale event.', [
                'transaction_id' => $payload['transaction_id'] ?? null,
                'date' => $payload['date'] ?? null,
            ]);

            return response('STALE EVENT', 200);
        }

        if (! $this->isValidCverify($payload)) {
            Log::warning('JVZoo IPN rejected: invalid cverify.', [
                'transaction_id' => $payload['transaction_id'] ?? null,
                'paykey' => $payload['paykey'] ?? null,
                'product_id' => $payload['product_id'] ?? null,
                'email' => $payload['customer_email'] ?? null,
            ]);

            return response('INVALID CVERIFY', 400);
        }

        if ($this->isDuplicateEvent($payload)) {
            return response('DUPLICATE', 200);
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
            $this->markProcessed($payload);

            return response('OK', 200);
        }

        if (in_array($transactionType, ['RFND', 'CGBK'], true)) {
            $this->downgradeUserOnReversal($payload);
        }

        $this->markProcessed($payload);

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
            $temporaryPassword = Str::password(16);
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make($temporaryPassword),
                'feature_tier' => $tier,
                'story_credits' => max(0, $targetCredits),
            ]);

            Log::info('JVZoo IPN: user created.', [
                'user_id' => $user->id,
                'email' => $email,
                'tier' => $tier->value,
                'product_id' => $productId,
            ]);

            $appName = (string) config('app.name', 'DreamForge AI');
            $loginUrl = url('/login');

            $this->sendLifecycleEmail(
                $user->email,
                "Your {$appName} account is ready",
                'Here is how to sign in',
                [
                    'Your purchase through JVZoo created your member account. Use the details below on the sign-in page.',
                    sprintf('Sign-in link: %s', $loginUrl),
                    'Step 1: Open the sign-in page using the button below or the link above.',
                    "Step 2: Email: {$user->email}",
                    "Step 3: Temporary password: {$temporaryPassword}",
                    'After you sign in you can change your password anytime under Settings if you prefer.',
                    sprintf('Your %s plan access is active with story credits ready to use.', ucfirst($tier->value)),
                ],
                'Open sign-in page',
                $loginUrl,
            );

            SendCeoOnboardingEmailJob::dispatch($user->id, 1)->delay(now()->addHour());
            SendCeoOnboardingEmailJob::dispatch($user->id, 2)->delay(now()->addDay());
            SendCeoOnboardingEmailJob::dispatch($user->id, 3)->delay(now()->addDays(3));

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

        $this->sendLifecycleEmail(
            $user->email,
            'Your plan access was updated',
            'Your account has been updated',
            [
                sprintf('Your current tier is now %s.', ucfirst($finalTier->value)),
                sprintf('Your story credits are now %d.', (int) $user->story_credits),
            ]
        );
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

        if (! (bool) config('jvzoo.auto_downgrade_on_reversal', false)) {
            Log::warning('JVZoo IPN reversal received; auto downgrade disabled.', [
                'user_id' => $user->id,
                'email' => $email,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'product_id' => $payload['product_id'] ?? null,
            ]);

            return;
        }

        $reversalTier = $this->tierForProductId((string) ($payload['product_id'] ?? ''));
        if (! $reversalTier instanceof FeatureTier) {
            return;
        }

        $currentTier = $user->feature_tier instanceof FeatureTier
            ? $user->feature_tier
            : FeatureTier::Basic;

        // Never downgrade accounts for a different product tier.
        if ($currentTier !== $reversalTier) {
            Log::info('JVZoo IPN reversal ignored: tier mismatch.', [
                'user_id' => $user->id,
                'current_tier' => $currentTier->value,
                'reversal_tier' => $reversalTier->value,
            ]);

            return;
        }

        $nextTier = match ($reversalTier) {
            FeatureTier::Elite => FeatureTier::Pro,
            FeatureTier::Pro, FeatureTier::Basic => FeatureTier::Basic,
        };

        $user->feature_tier = $nextTier;
        $user->story_credits = min((int) $user->story_credits, (int) (config('jvzoo.tier_credits.'.$nextTier->value, 30)));
        $user->save();

        Log::info('JVZoo IPN: user downgraded after reversal.', [
            'user_id' => $user->id,
            'email' => $email,
            'transaction_type' => strtoupper((string) ($payload['transaction_type'] ?? '')),
            'transaction_id' => $payload['transaction_id'] ?? null,
        ]);

        $this->sendLifecycleEmail(
            $user->email,
            'Your account plan was adjusted',
            'Your account tier has changed',
            [
                sprintf('Your account tier is now %s.', ucfirst($nextTier->value)),
                sprintf('Your available credits are now %d.', (int) $user->story_credits),
            ]
        );
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

        $signatureBase = implode('|', [
            trim($paykey),
            trim($customerEmail),
            trim($productName),
            trim($transactionType),
            trim($date),
        ]).$secret;

        $calculated = sha1(mb_convert_encoding($signatureBase, 'UTF-8'));
        $calculated = strtoupper(substr($calculated, 0, 8));

        return hash_equals($calculated, $incoming);
    }

    private function isFreshEvent(array $payload): bool
    {
        $rawDate = (string) ($payload['date'] ?? '');
        if ($rawDate === '') {
            return false;
        }

        try {
            $eventAt = Carbon::parse($rawDate);
        } catch (\Throwable) {
            return false;
        }

        $maxAgeMinutes = max(1, (int) config('jvzoo.max_event_age_minutes', 120));

        return $eventAt->greaterThanOrEqualTo(now()->subMinutes($maxAgeMinutes));
    }

    private function fingerprintFor(array $payload): string
    {
        $parts = [
            (string) ($payload['transaction_id'] ?? ''),
            (string) ($payload['paykey'] ?? ''),
            strtolower((string) ($payload['customer_email'] ?? '')),
            (string) ($payload['product_id'] ?? ''),
            strtoupper((string) ($payload['transaction_type'] ?? '')),
            strtoupper((string) ($payload['status'] ?? '')),
            (string) ($payload['date'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function isDuplicateEvent(array $payload): bool
    {
        $fingerprint = $this->fingerprintFor($payload);

        $event = JvzooIpnEvent::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'transaction_id' => (string) ($payload['transaction_id'] ?? ''),
                'paykey' => (string) ($payload['paykey'] ?? ''),
                'customer_email' => strtolower((string) ($payload['customer_email'] ?? '')),
                'product_id' => (string) ($payload['product_id'] ?? ''),
                'transaction_type' => strtoupper((string) ($payload['transaction_type'] ?? '')),
                'status' => strtoupper((string) ($payload['status'] ?? '')),
                'event_at' => $this->parseEventAt((string) ($payload['date'] ?? '')),
                'payload' => $payload,
                'is_duplicate' => false,
            ]
        );

        if ($event->wasRecentlyCreated) {
            return false;
        }

        $event->forceFill(['is_duplicate' => true])->save();

        return true;
    }

    private function parseEventAt(string $rawDate): ?Carbon
    {
        if ($rawDate === '') {
            return null;
        }

        try {
            return Carbon::parse($rawDate);
        } catch (\Throwable) {
            return null;
        }
    }

    private function markProcessed(array $payload): void
    {
        $fingerprint = $this->fingerprintFor($payload);

        JvzooIpnEvent::query()
            ->where('fingerprint', $fingerprint)
            ->update(['processed_at' => now()]);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function sendLifecycleEmail(
        string $to,
        string $subject,
        string $headline,
        array $lines,
        ?string $ctaLabel = null,
        ?string $ctaUrl = null,
    ): void {
        try {
            Mail::to($to)->queue(
                new UserNotificationMail(
                    subjectLine: $subject,
                    headline: $headline,
                    lines: $lines,
                    ctaLabel: $ctaLabel ?? 'Open Dashboard',
                    ctaUrl: $ctaUrl ?? url('/dashboard'),
                )
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send JVZoo lifecycle email.', [
                'email' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
