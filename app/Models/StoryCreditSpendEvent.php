<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryCreditSpendEvent extends Model
{
    protected $fillable = [
        'user_id',
        'idempotency_key',
        'kind',
        'cost',
        'spent_at',
    ];

    protected function casts(): array
    {
        return [
            'spent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
