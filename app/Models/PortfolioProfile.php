<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'headline',
    'tagline',
    'about',
    'location',
    'email',
    'phone',
    'current_role',
    'current_company',
    'portfolio_url',
    'stats',
    'services',
    'experience',
    'skills',
    'education',
    'social',
    'meta',
    'is_public',
])]
class PortfolioProfile extends Model
{
    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'services' => 'array',
            'experience' => 'array',
            'skills' => 'array',
            'education' => 'array',
            'social' => 'array',
            'meta' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
