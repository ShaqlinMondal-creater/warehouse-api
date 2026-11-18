<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'label',
    'provider',
    'model_id',
    'api_token',
    'is_enabled',
    'is_default',
    'expires_at',
    'sort_order',
    'notes',
])]
#[Hidden(['api_token'])]
class AiModel extends Model
{
    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
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

    public function hasApiToken(): bool
    {
        return filled($this->api_token);
    }

    public function resolvedApiToken(): ?string
    {
        $token = $this->api_token;
        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }

        return match ($this->provider) {
            'openai' => $this->nullableEnv(config('services.openai.api_key')),
            'claude' => $this->nullableEnv(config('services.anthropic.api_key')),
            'openrouter' => $this->nullableEnv(config('services.openrouter.api_key')),
            default => null,
        };
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

    private function nullableEnv(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
