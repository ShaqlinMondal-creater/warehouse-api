<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesOwnedResources;
use App\Http\Requests\BidWorkspaces\StoreBidWorkspaceRequest;
use App\Http\Requests\BidWorkspaces\UpdateBidWorkspaceRequest;
use App\Models\BidWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BidWorkspaceController extends Controller
{
    use AuthorizesOwnedResources;

    public function index(Request $request): JsonResponse
    {
        $items = BidWorkspace::query()
            ->where('user_id', $request->user()->id)
            ->withCount('bids')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(StoreBidWorkspaceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['currency'] = $data['currency'] ?? 'INR';

        $item = BidWorkspace::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function show(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        return response()->json([
            'data' => $bidWorkspace->loadCount('bids'),
        ]);
    }

    public function update(UpdateBidWorkspaceRequest $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);
        $bidWorkspace->update($request->validated());

        return response()->json(['data' => $bidWorkspace->fresh()]);
    }

    public function destroy(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);
        $bidWorkspace->delete();

        return response()->json(['ok' => true]);
    }
}
