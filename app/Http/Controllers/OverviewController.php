<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\BidWorkspace;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\WorkProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $workspaces = BidWorkspace::query()
            ->where('user_id', $user->id)
            ->withCount('bids')
            ->orderBy('sort_order')
            ->get();

        $bidStats = Bid::query()
            ->whereIn('bid_workspace_id', $workspaces->pluck('id'))
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'active' then 1 else 0 end) as active")
            ->selectRaw("sum(case when status = 'won' then 1 else 0 end) as won")
            ->first();

        return response()->json([
            'portfolio_count' => WorkProject::query()->where('user_id', $user->id)->count(),
            'plan_count' => Plan::query()->where('user_id', $user->id)->count(),
            'workspaces' => $workspaces,
            'bids' => [
                'total' => (int) ($bidStats->total ?? 0),
                'active' => (int) ($bidStats->active ?? 0),
                'won' => (int) ($bidStats->won ?? 0),
            ],
            'settings' => Setting::query()
                ->where('user_id', $user->id)
                ->get()
                ->map->toPublicArray()
                ->values(),
        ]);
    }
}
