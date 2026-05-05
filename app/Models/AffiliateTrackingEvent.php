<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateTrackingEvent extends Model
{
    protected $fillable = [
        'affiliate_partner_id',
        'event_type',
        'visitor_token',
        'email',
        'ip_address',
        'user_agent',
        'landing_url',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class, 'affiliate_partner_id');
    }
}
