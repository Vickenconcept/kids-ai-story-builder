<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JvzooIpnEvent extends Model
{
    protected $fillable = [
        'fingerprint',
        'transaction_id',
        'paykey',
        'customer_email',
        'product_id',
        'transaction_type',
        'status',
        'event_at',
        'is_duplicate',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'is_duplicate' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
