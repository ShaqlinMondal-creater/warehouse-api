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

    /** Simple analytics for one workspace */
    public function analytics(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $bids = $bidWorkspace->bids();

        $total = (clone $bids)->count();
        $active = (clone $bids)->where('status', 'active')->count();
        $won = (clone $bids)->where('status', 'won')->count();
        $avgMyBid = (clone $bids)->whereNotNull('my_bid')->avg('my_bid');

        return response()->json([
            'workspace' => $bidWorkspace->only(['id', 'name', 'slug', 'platform', 'currency']),
            'stats' => [
                'total' => $total,
                'active' => $active,
                'won' => $won,
                'win_rate' => $total > 0 ? round(($won / $total) * 100, 1) : 0,
                'avg_my_bid' => $avgMyBid !== null ? round((float) $avgMyBid, 2) : null,
            ],
        ]);
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
            'my_rank' => ['nullable', 'integer', 'min:1'],
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
