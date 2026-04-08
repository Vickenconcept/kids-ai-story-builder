<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayPalCheckoutIntent extends Model
{
    protected $table = 'paypal_checkout_intents';

    protected $fillable = [
        'user_id',
        'domain',
        'target_id',
        'amount_cents',
        'currency',
        'provider_order_id',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
