<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bid_workspace_id',
    'external_key',
    'project_name',
    'client_name',
    'total_bids',
    'average_bid',
    'average_bid_raw',
    'my_bid',
    'my_bid_raw',
    'bid_type',
    'my_rank',
    'updated_rank',
    'client_viewed',
    'status',
    'chatting',
    'bid_placed_at',
    'last_synced_at',
    'source',
    'notes',
    'meta',
])]
class Bid extends Model
{
    protected function casts(): array
    {
        return [
            'average_bid' => 'decimal:2',
            'my_bid' => 'decimal:2',
            'client_viewed' => 'boolean',
            'bid_placed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(BidWorkspace::class, 'bid_workspace_id');
    }
}
