<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exam_subject_id',
    'set_key',
    'questions_file',
    'total',
    'answered',
    'correct',
    'incorrect',
    'accuracy_percent',
    'answers',
    'results',
    'started_at',
    'submitted_at',
])]
class ExamPracticeAttempt extends Model
{
    protected $table = 'exam_practice_attempts';

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'answered' => 'integer',
            'correct' => 'integer',
            'incorrect' => 'integer',
            'accuracy_percent' => 'float',
            'answers' => 'array',
            'results' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
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
