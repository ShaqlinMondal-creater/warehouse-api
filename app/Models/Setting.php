<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'group',
    'key',
    'value',
    'is_secret',
])]
#[Hidden([])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Safe value for API responses (mask secrets). */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->is_secret ? ($this->value ? '********' : null) : $this->value,
            'is_secret' => $this->is_secret,
            'has_value' => filled($this->value),
        ];
    }
}
