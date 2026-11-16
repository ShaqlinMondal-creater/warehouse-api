<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'label',
    'provider',
    'model_id',
    'is_enabled',
    'is_default',
    'expires_at',
    'sort_order',
    'notes',
])]
class AiModel extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'expires_at' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function expiryStatus(): string
    {
        if ($this->expires_at === null) {
            return 'none';
        }

        if ($this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->expires_at->lte(now()->addDays(14))) {
            return 'soon';
        }

        return 'active';
    }
}
