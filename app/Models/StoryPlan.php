<?php

namespace App\Models;

use App\Enums\FeatureTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryPlan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'tier',
        'included_credits',
        'price_cents',
        'currency',
        'sort_order',
        'is_active',
        'is_featured',
        'feature_list',
    ];

    protected function casts(): array
    {
        return [
            'tier' => FeatureTier::class,
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'feature_list' => 'array',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PlanPurchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price_cents');
    }
}
