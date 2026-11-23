<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\BidWorkspace;

final class BidImportService
{
    public function __construct(private readonly BidComparison $comparison) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{added: int, updated: int, skipped: int, data: list<Bid>}
     */
    public function import(BidWorkspace $workspace, array $rows): array
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $saved = [];

        foreach ($rows as $row) {
            $existing = Bid::query()
                ->where('bid_workspace_id', $workspace->id)
                ->where('project_name', $row['project_name'])
                ->first();

            if ($existing && $this->comparison->isSame($existing, $row)) {
                $skipped++;
                $saved[] = $existing;

                continue;
            }

            $incomingRank = array_key_exists('my_rank', $row) ? $row['my_rank'] : null;
            $incomingRank = $incomingRank === '' ? null : $incomingRank;

            if ($existing) {
                $payload = [
                    'total_bids' => $row['total_bids'] ?? null,
                    'average_bid' => $row['average_bid'] ?? null,
                    'average_bid_raw' => $row['average_bid_raw'] ?? null,
                    'my_bid' => $row['my_bid'] ?? null,
                    'my_bid_raw' => $row['my_bid_raw'] ?? null,
                    'bid_type' => $row['bid_type'] ?? null,
                    'source' => 'screenshot',
                    'last_synced_at' => now(),
                ];

                // Only overwrite viewed when the client explicitly sent it (do not wipe on sync).
                if (array_key_exists('client_viewed', $row)) {
                    $payload['client_viewed'] = (bool) $row['client_viewed'];
                }

                // Preserve original my_rank; store newer rank in updated_rank when it changes.
                if ($incomingRank !== null) {
                    $effective = $existing->updated_rank !== null && $existing->updated_rank !== ''
                        ? (string) $existing->updated_rank
                        : ($existing->my_rank !== null && $existing->my_rank !== '' ? (string) $existing->my_rank : null);

                    if ($effective === null && ($existing->my_rank === null || $existing->my_rank === '')) {
                        $payload['my_rank'] = $incomingRank;
                    } elseif ((string) $incomingRank !== (string) $effective) {
                        $payload['updated_rank'] = $incomingRank;
                    }
                }

                // Only refresh bid_placed_at / status when provided and useful
                if (! empty($row['bid_placed_at']) && ! $existing->bid_placed_at) {
                    $payload['bid_placed_at'] = $row['bid_placed_at'];
                }
                if (! empty($row['status'])) {
                    $payload['status'] = $row['status'];
                }

                $existing->update($payload);
                $bid = $existing->fresh();
                $updated++;
            } else {
                $bid = Bid::query()->create([
                    'bid_workspace_id' => $workspace->id,
                    'project_name' => $row['project_name'],
                    'total_bids' => $row['total_bids'] ?? null,
                    'my_rank' => $incomingRank,
                    'updated_rank' => null,
                    'client_viewed' => array_key_exists('client_viewed', $row)
                        ? (bool) $row['client_viewed']
                        : false,
                    'average_bid' => $row['average_bid'] ?? null,
                    'average_bid_raw' => $row['average_bid_raw'] ?? null,
                    'my_bid' => $row['my_bid'] ?? null,
                    'my_bid_raw' => $row['my_bid_raw'] ?? null,
                    'bid_type' => $row['bid_type'] ?? null,
                    'bid_placed_at' => $row['bid_placed_at'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'source' => 'screenshot',
                    'last_synced_at' => now(),
                ]);
                $added++;
            }

            $saved[] = $bid;
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'skipped' => $skipped,
            'data' => $saved,
        ];
    }
}
