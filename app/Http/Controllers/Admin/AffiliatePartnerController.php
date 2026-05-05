<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePartner;
use App\Models\AffiliateTrackingEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePartnerController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $partners = AffiliatePartner::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (AffiliatePartner $partner) {
                $base = AffiliateTrackingEvent::query()
                    ->where('affiliate_partner_id', $partner->id);

                return [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'email' => $partner->email,
                    'slug' => $partner->slug,
                    'is_active' => (bool) $partner->is_active,
                    'notes' => $partner->notes,
                    'capture_url' => route('affiliate.capture', ['partner' => $partner->slug]),
                    'stats_url' => $partner->stats_token
                        ? route('affiliate.stats', ['partner' => $partner->slug, 'token' => $partner->stats_token])
                        : null,
                    'stats' => [
                        'clicks' => (clone $base)->where('event_type', 'click')->count(),
                        'optins' => (clone $base)->where('event_type', 'optin')->count(),
                        'sales' => (clone $base)->where('event_type', 'sale')->count(),
                    ],
                    'created_at' => $partner->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/Affiliates/Index', [
            'partners' => $partners,
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:190', 'unique:affiliate_partners,email'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', 'unique:affiliate_partners,slug'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) $data['name']);
        }
        if ($slug === '') {
            $slug = 'partner-'.Str::lower(Str::random(8));
        }

        AffiliatePartner::query()->create([
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'slug' => $slug,
            'stats_token' => Str::lower(Str::random(32)),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
        ]);

        return back()->with('success', 'Affiliate partner created.');
    }

    public function update(Request $request, AffiliatePartner $partner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:190', 'unique:affiliate_partners,email,'.$partner->id],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', 'unique:affiliate_partners,slug,'.$partner->id],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $partner->update([
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'slug' => trim((string) $data['slug']),
            'is_active' => (bool) $data['is_active'],
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
        ]);

        return back()->with('success', 'Affiliate partner updated.');
    }
}
