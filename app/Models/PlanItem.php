<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_id',
    'title',
    'detail',
    'progress',
    'status',
    'priority',
    'due_on',
    'sort_order',
])]
class PlanItem extends Model
{
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
