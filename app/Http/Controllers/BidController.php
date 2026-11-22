<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesOwnedResources;
use App\Http\Requests\Bids\StoreBidRequest;
use App\Http\Requests\Bids\UpdateBidRequest;
use App\Models\Bid;
use App\Models\BidWorkspace;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    use AuthorizesOwnedResources;

    public function index(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $bids = $bidWorkspace->bids()
            ->orderByDesc('bid_placed_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $bids]);
    }

    public function store(StoreBidRequest $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);
        $data = $request->validated();
        $data['bid_workspace_id'] = $bidWorkspace->id;
        $data['last_synced_at'] = now();

        $bid = Bid::query()->updateOrCreate(
            [
                'bid_workspace_id' => $bidWorkspace->id,
                'project_name' => $data['project_name'],
            ],
            $data,
        );

        return response()->json(['data' => $bid], 201);
    }

    public function show(Request $request, Bid $bid): JsonResponse
    {
        $this->authorizeBid($request, $bid);

        return response()->json(['data' => $bid]);
    }

    public function update(UpdateBidRequest $request, Bid $bid): JsonResponse
    {
        $this->authorizeBid($request, $bid);
        $bid->update($request->validated());

        return response()->json(['data' => $bid->fresh()]);
    }

    public function destroy(Request $request, Bid $bid): JsonResponse
    {
        $this->authorizeBid($request, $bid);
        $bid->delete();

        return response()->json(['ok' => true]);
    }

    public function analytics(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $query = $bidWorkspace->bids();
        $total = (clone $query)->count();
        $active = (clone $query)->where('status', 'active')->count();
        $watching = (clone $query)->where('status', 'watching')->count();
        $won = (clone $query)->where('status', 'won')->count();
        $lost = (clone $query)->where('status', 'lost')->count();
        $avgMyBid = (clone $query)->whereNotNull('my_bid')->avg('my_bid');
        $avgMarket = (clone $query)->whereNotNull('average_bid')->avg('average_bid');
        $winRate = $total > 0 ? round(($won / $total) * 100, 1) : 0.0;
        $currency = $bidWorkspace->currency ?: 'INR';

        return response()->json([
            'workspace' => $bidWorkspace->only(['id', 'name', 'slug', 'platform', 'currency']),
            'stats' => [
                'total' => $total,
                'active' => $active,
                'watching' => $watching,
                'won' => $won,
                'lost' => $lost,
                'win_rate' => $winRate,
                'avg_my_bid' => $avgMyBid !== null ? round((float) $avgMyBid, 2) : null,
                'avg_market_bid' => $avgMarket !== null ? round((float) $avgMarket, 2) : null,
                'avg_my_bid_label' => Money::label($avgMyBid, $currency),
                'avg_market_bid_label' => Money::label($avgMarket, $currency),
                'win_rate_label' => rtrim(rtrim(number_format($winRate, 1, '.', ''), '0'), '.').'%',
            ],
        ]);
    }
}
