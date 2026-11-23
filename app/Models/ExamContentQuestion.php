<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exam_subject_id',
    'external_id',
    'set_key',
    'question_no',
    'chapter_code',
    'stem',
    'options',
    'correct_answer',
    'explanation',
    'source_questions_file',
    'source_answers_file',
    'content_hash',
])]
class ExamContentQuestion extends Model
{
    protected $table = 'exam_content_questions';

    protected function casts(): array
    {
        return [
            'question_no' => 'integer',
            'options' => 'array',
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
