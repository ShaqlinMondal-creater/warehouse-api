<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BidWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BidWorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = BidWorkspace::query()
            ->where('user_id', $request->user()->id)
            ->withCount('bids')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'platform' => ['required', 'in:freelancer,upwork,other'],
            'currency' => ['nullable', 'string', 'max:8'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['currency'] = $data['currency'] ?? 'INR';

        $item = BidWorkspace::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function show(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeOwner($request, $bidWorkspace);

        return response()->json([
            'data' => $bidWorkspace->loadCount('bids'),
        ]);
    }

    public function update(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeOwner($request, $bidWorkspace);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'platform' => ['sometimes', 'in:freelancer,upwork,other'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $bidWorkspace->update($data);

        return response()->json(['data' => $bidWorkspace->fresh()]);
    }

    public function destroy(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeOwner($request, $bidWorkspace);
        $bidWorkspace->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeOwner(Request $request, BidWorkspace $workspace): void
    {
        abort_unless($workspace->user_id === $request->user()->id, 404);
    }
}
