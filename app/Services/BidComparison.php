<?php

namespace App\Services;

use App\Models\Bid;

final class BidComparison
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function isSame(Bid $existing, array $row): bool
    {
        $incomingViewed = array_key_exists('client_viewed', $row) ? $row['client_viewed'] : false;
        $existingViewed = $existing->client_viewed ?? false;

        return $this->sameInt($existing->total_bids, $row['total_bids'] ?? null)
            && $this->sameRank($existing->my_rank, $row['my_rank'] ?? null)
            && $this->sameNumber($existing->my_bid, $row['my_bid'] ?? null)
            && $this->sameNumber($existing->average_bid, $row['average_bid'] ?? null)
            && (bool) $existingViewed === (bool) $incomingViewed;
    }

    private function sameNumber(mixed $a, mixed $b): bool
    {
        if ($a === null && ($b === null || $b === '')) {
            return true;
        }
        if ($a === null || $b === null || $b === '') {
            return false;
        }

        return abs((float) $a - (float) $b) < 0.001;
    }

    private function sameInt(mixed $a, mixed $b): bool
    {
        if ($a === null && ($b === null || $b === '')) {
            return true;
        }
        if ($a === null || $b === null || $b === '') {
            return false;
        }

        return (int) $a === (int) $b;
    }

    private function sameRank(mixed $a, mixed $b): bool
    {
        $left = $a === null || $a === '' ? null : (string) $a;
        $right = $b === null || $b === '' ? null : (string) $b;

        return $left === $right;
    }
}
