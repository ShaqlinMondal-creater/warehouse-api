<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = WorkProject::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);

        $item = WorkProject::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function show(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);

        return response()->json(['data' => $workProject]);
    }

    public function update(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);
        $workProject->update($this->validated($request, partial: true));

        return response()->json(['data' => $workProject->fresh()]);
    }

    public function destroy(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);
        $workProject->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? ['sometimes'] : ['required'];

        return $request->validate([
            'title' => [...$required, 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'role' => ['nullable', 'string', 'max:120'],
            'stack' => ['nullable', 'array'],
            'stack.*' => ['string', 'max:80'],
            'platform_or_client' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', 'in:done,ongoing,paused'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date'],
            'url' => ['nullable', 'url', 'max:255'],
            'repo_url' => ['nullable', 'url', 'max:255'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);
    }

    private function authorizeOwner(Request $request, WorkProject $workProject): void
    {
        abort_unless($workProject->user_id === $request->user()->id, 404);
    }
}
