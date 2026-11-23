<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exam_subject_id',
    'code',
    'title',
    'status',
    'mcq_used',
    'mcq_target',
    'meta',
    'content_hash',
])]
class ExamContentChapter extends Model
{
    protected $table = 'exam_content_chapters';

    protected function casts(): array
    {
        return [
            'mcq_used' => 'integer',
            'mcq_target' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class, 'exam_subject_id');
    }
}
