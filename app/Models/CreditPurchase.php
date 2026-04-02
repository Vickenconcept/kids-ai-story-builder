<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'credit_pack_id',
        'pack_name',
        'credits_awarded',
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
            'raw_payload' => 'array',
            'purchased_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(CreditPack::class, 'credit_pack_id');
    }
}
