<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'exam_subject_id',
    'original_name',
    'bytes',
    'status',
    'storage_path',
    'files_indexed',
    'message',
    'progress',
    'synced_at',
])]
class ExamSync extends Model
{
    protected $table = 'exam_syncs';

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'files_indexed' => 'integer',
            'progress' => 'array',
            'synced_at' => 'datetime',
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
