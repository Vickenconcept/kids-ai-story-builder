<?php

namespace App\Models;

use App\Enums\FeatureTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'story_plan_id',
        'plan_name',
        'tier',
        'credits_floor',
        'amount_cents',
        'currency',
        'provider',
        'provider_order_id',
        'provider_capture_id',
        'status',
        'raw_payload',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'tier' => FeatureTier::class,
            'raw_payload' => 'array',
            'purchased_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StoryPlan::class, 'story_plan_id');
    }
}
