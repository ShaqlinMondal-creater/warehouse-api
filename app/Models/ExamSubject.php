<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'exam_set_id',
    'slug',
    'title',
    'is_enabled',
    'sort_order',
])]
class ExamSubject extends Model
{
    protected $table = 'exam_subjects';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function examSet(): BelongsTo
    {
        return $this->belongsTo(ExamSet::class, 'exam_set_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(ExamSubjectState::class, 'exam_subject_id');
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(ExamSync::class, 'exam_subject_id');
    }
}
