<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'title',
    'slug',
    'summary',
    'description',
    'role',
    'stack',
    'platform_or_client',
    'status',
    'started_on',
    'ended_on',
    'url',
    'repo_url',
    'cover_path',
    'sort_order',
    'is_public',
    'meta',
])]
class WorkProject extends Model
{
    protected function casts(): array
    {
        return [
            'stack' => 'array',
            'meta' => 'array',
            'is_public' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
