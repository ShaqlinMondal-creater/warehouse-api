<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    'sort_order',
    'is_public',
    'meta',
])]
class WorkProject extends Model
{
    protected $appends = ['cover_url', 'images'];

    /** @var list<array{id:int,url:?string,sort_order:int,original_name:?string}>|null */
    private ?array $galleryCache = null;

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

    public function uploads(): MorphMany
    {
        return $this->morphMany(Upload::class, 'uploadable');
    }

    public function gallery(): MorphMany
    {
        return $this->morphMany(Upload::class, 'uploadable')
            ->where('collection', Upload::COLLECTION_GALLERY)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** First gallery image used as card cover. */
    public function getCoverUrlAttribute(): ?string
    {
        return $this->galleryImages()[0]['url'] ?? null;
    }

    /** @return list<array{id:int,url:?string,sort_order:int,original_name:?string}> */
    public function getImagesAttribute(): array
    {
        return $this->galleryImages();
    }

    /** @return list<array{id:int,url:?string,sort_order:int,original_name:?string}> */
    private function galleryImages(): array
    {
        if ($this->galleryCache !== null) {
            return $this->galleryCache;
        }

        $rows = $this->relationLoaded('gallery')
            ? $this->gallery
            : $this->gallery()->get();

        return $this->galleryCache = $rows->values()->map(fn (Upload $u) => [
            'id' => $u->id,
            'url' => $u->url,
            'sort_order' => (int) $u->sort_order,
            'original_name' => $u->original_name,
        ])->all();
    }
}
