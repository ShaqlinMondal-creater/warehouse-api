<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\BidWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function index(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $bids = $bidWorkspace->bids()
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $bids]);
    }

    public function store(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);
        $data = $this->validated($request);
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

    public function update(Request $request, Bid $bid): JsonResponse
    {
        $this->authorizeBid($request, $bid);
        $bid->update($this->validated($request, partial: true));

        return response()->json(['data' => $bid->fresh()]);
    }

    public function destroy(Request $request, Bid $bid): JsonResponse
    {
        $this->authorizeBid($request, $bid);
        $bid->delete();

        return response()->json(['ok' => true]);
    }

    /** Metrics + list stats for one workspace */
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
                'avg_my_bid_label' => $this->moneyLabel($avgMyBid, $currency),
                'avg_market_bid_label' => $this->moneyLabel($avgMarket, $currency),
                'win_rate_label' => rtrim(rtrim(number_format($winRate, 1, '.', ''), '0'), '.').'%',
            ],
        ]);
    }

    private function moneyLabel(mixed $amount, string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        $value = (float) $amount;
        $symbol = match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '₹',
        };

        if ($value >= 1000) {
            return $symbol.rtrim(rtrim(number_format($value / 1000, 1, '.', ''), '0'), '.').'k';
        }

        return $symbol.number_format($value, $value == floor($value) ? 0 : 2);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes'] : ['required'];

        return $request->validate([
            'project_name' => [...$required, 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:190'],
            'external_key' => ['nullable', 'string', 'max:190'],
            'total_bids' => ['nullable', 'integer', 'min:0'],
            'average_bid' => ['nullable', 'numeric'],
            'average_bid_raw' => ['nullable', 'string', 'max:80'],
            'my_bid' => ['nullable', 'numeric'],
            'my_bid_raw' => ['nullable', 'string', 'max:80'],
            'bid_type' => ['nullable', 'in:fixed,hourly'],
            'my_rank' => ['nullable', 'string', 'max:20'],
            'client_viewed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,watching,won,lost,archived'],
            'chatting' => ['nullable', 'string', 'max:80'],
            'bid_placed_at' => ['nullable', 'date'],
            'source' => ['nullable', 'in:manual,screenshot,import'],
            'notes' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
        ]);
    }

    private function authorizeWorkspace(Request $request, BidWorkspace $workspace): void
    {
        abort_unless($workspace->user_id === $request->user()->id, 404);
    }

    private function authorizeBid(Request $request, Bid $bid): void
    {
        $bid->loadMissing('workspace');
        abort_unless($bid->workspace?->user_id === $request->user()->id, 404);
    }
}
