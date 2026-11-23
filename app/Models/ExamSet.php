<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'title',
    'year',
    'is_enabled',
    'sort_order',
])]
class ExamSet extends Model
{
    protected $table = 'exam_sets';

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class, 'exam_set_id')->orderBy('sort_order');
    }
}
