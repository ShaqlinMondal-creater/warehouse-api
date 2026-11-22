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

            $bid = Bid::query()->updateOrCreate(
                [
                    'bid_workspace_id' => $workspace->id,
                    'project_name' => $row['project_name'],
                ],
                [
                    'total_bids' => $row['total_bids'] ?? null,
                    'my_rank' => $row['my_rank'] ?? null,
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
                ],
            );

            if ($existing) {
                $updated++;
            } else {
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
