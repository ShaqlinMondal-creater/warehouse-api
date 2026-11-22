<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'disk',
    'path',
    'original_name',
    'mime',
    'size',
    'collection',
    'uploadable_type',
    'uploadable_id',
    'sort_order',
    'meta',
])]
class Upload extends Model
{
    public const COLLECTION_AVATAR = 'avatar';

    public const COLLECTION_GALLERY = 'gallery';

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::deleting(function (Upload $upload): void {
            if ($upload->path) {
                Storage::disk($upload->disk ?: 'public')->delete($upload->path);
            }
        });
    }
}
