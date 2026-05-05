<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePartner;
use App\Models\AffiliateTrackingEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateCampaignController extends Controller
{
    private const VISITOR_COOKIE = 'affiliate_visitor_token';

    public function show(Request $request, AffiliatePartner $partner): Response
    {
        abort_unless($partner->is_active, 404);

        $visitorToken = $this->resolveVisitorToken($request);

        $this->trackEvent($request, $partner, 'click', $visitorToken);

        $this->queueVisitorCookie($visitorToken);

        return Inertia::render('Affiliate/Capture', [
            'partner' => [
                'name' => $partner->name ?: $partner->slug,
                'slug' => $partner->slug,
            ],
        ]);
    }

    public function stats(Request $request, AffiliatePartner $partner): Response
    {
        abort_unless(
            $partner->stats_token !== null &&
            hash_equals((string) $partner->stats_token, trim((string) $request->query('token', ''))),
            403
        );

        $base = AffiliateTrackingEvent::query()->where('affiliate_partner_id', $partner->id);

        $recentOptins = (clone $base)
            ->where('event_type', 'optin')
            ->orderByDesc('occurred_at')
            ->limit(30)
            ->get(['email', 'occurred_at', 'utm_source', 'utm_campaign'])
            ->map(fn ($e) => [
                'email' => $e->email,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'utm_source' => $e->utm_source,
                'utm_campaign' => $e->utm_campaign,
            ]);

        $recentSales = (clone $base)
            ->where('event_type', 'sale')
            ->orderByDesc('occurred_at')
            ->limit(30)
            ->get(['email', 'occurred_at', 'meta'])
            ->map(fn ($e) => [
                'email' => $e->email,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'product_id' => data_get($e->meta, 'product_id'),
                'transaction_id' => data_get($e->meta, 'transaction_id'),
            ]);

        return Inertia::render('Affiliate/Stats', [
            'partner' => [
                'name' => $partner->name ?: $partner->slug,
                'slug' => $partner->slug,
            ],
            'stats' => [
                'clicks' => (clone $base)->where('event_type', 'click')->count(),
                'optins' => (clone $base)->where('event_type', 'optin')->count(),
                'sales' => (clone $base)->where('event_type', 'sale')->count(),
            ],
            'recent_optins' => $recentOptins,
            'recent_sales' => $recentSales,
        ]);
    }

    public function captureLead(Request $request, AffiliatePartner $partner): RedirectResponse
    {
        abort_unless($partner->is_active, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:190'],
        ]);

        $visitorToken = $this->resolveVisitorToken($request);
        $email = strtolower(trim((string) $validated['email']));
        $name = trim((string) ($validated['name'] ?? ''));

        $existing = AffiliateTrackingEvent::query()
            ->where('affiliate_partner_id', $partner->id)
            ->where('event_type', 'optin')
            ->where('email', $email)
            ->first();

        if (! $existing) {
            $this->trackEvent($request, $partner, 'optin', $visitorToken, $email, [
                'name' => $name,
            ]);
        }

        $this->queueVisitorCookie($visitorToken);

        $destination = (string) config('services.affiliate.redirect_url', '/sales');

        return redirect()->away($destination);
    }

    private function resolveVisitorToken(Request $request): string
    {
        $existing = trim((string) $request->cookie(self::VISITOR_COOKIE, ''));
        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }

    private function queueVisitorCookie(string $token): void
    {
        Cookie::queue(cookie(
            self::VISITOR_COOKIE,
            $token,
            60 * 24 * 30, // 30 days
            '/',
            null,
            false,
            true,
            false,
            'Lax'
        ));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function trackEvent(
        Request $request,
        AffiliatePartner $partner,
        string $eventType,
        string $visitorToken,
        ?string $email = null,
        array $meta = [],
    ): void {
        AffiliateTrackingEvent::query()->create([
            'affiliate_partner_id' => $partner->id,
            'event_type' => $eventType,
            'visitor_token' => $visitorToken,
            'email' => $email ? strtolower($email) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'landing_url' => mb_substr((string) $request->fullUrl(), 0, 2000),
            'referrer' => mb_substr((string) $request->headers->get('referer', ''), 0, 2000),
            'utm_source' => $this->trimNullable($request->query('utm_source')),
            'utm_medium' => $this->trimNullable($request->query('utm_medium')),
            'utm_campaign' => $this->trimNullable($request->query('utm_campaign')),
            'utm_content' => $this->trimNullable($request->query('utm_content')),
            'utm_term' => $this->trimNullable($request->query('utm_term')),
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
    }

    private function trimNullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 255);
    }
}
