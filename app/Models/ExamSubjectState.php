<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exam_subject_id',
    'storage_path',
    'last_synced_at',
    'last_original_name',
    'last_bytes',
    'file_count',
    'progress',
])]
class ExamSubjectState extends Model
{
    protected $table = 'exam_subject_states';

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_bytes' => 'integer',
            'file_count' => 'integer',
            'progress' => 'array',
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
