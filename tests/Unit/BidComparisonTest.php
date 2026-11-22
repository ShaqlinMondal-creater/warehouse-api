<?php

namespace Tests\Unit;

use App\Models\Bid;
use App\Services\BidComparison;
use PHPUnit\Framework\TestCase;

class BidComparisonTest extends TestCase
{
    public function test_same_bid_fingerprint_matches(): void
    {
        $bid = new Bid([
            'total_bids' => 100,
            'my_rank' => '100+',
            'my_bid' => 20000,
            'average_bid' => 15000,
            'client_viewed' => false,
        ]);

        $comparison = new BidComparison;

        $this->assertTrue($comparison->isSame($bid, [
            'total_bids' => 100,
            'my_rank' => '100+',
            'my_bid' => 20000,
            'average_bid' => 15000,
            'client_viewed' => false,
        ]));
    }

    public function test_rank_change_is_not_same(): void
    {
        $bid = new Bid([
            'total_bids' => 100,
            'my_rank' => '96',
            'my_bid' => 20000,
            'average_bid' => 15000,
            'client_viewed' => false,
        ]);

        $comparison = new BidComparison;

        $this->assertFalse($comparison->isSame($bid, [
            'total_bids' => 100,
            'my_rank' => '100+',
            'my_bid' => 20000,
            'average_bid' => 15000,
            'client_viewed' => false,
        ]));
    }
}
