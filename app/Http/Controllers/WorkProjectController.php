<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\WorkProject;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkProjectController extends Controller
{
    public function __construct(private UploadService $uploads) {}

    public function index(Request $request): JsonResponse
    {
        $items = WorkProject::query()
            ->where('user_id', $request->user()->id)
            ->with('gallery')
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

        return response()->json(['data' => $item->load('gallery')], 201);
    }

    public function show(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);

        return response()->json(['data' => $workProject->load('gallery')]);
    }

    public function update(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);
        $workProject->update($this->validated($request, partial: true));

        return response()->json(['data' => $workProject->fresh()->load('gallery')]);
    }

    public function destroy(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);
        $this->uploads->clearCollection($workProject, Upload::COLLECTION_GALLERY);
        $workProject->delete();

        return response()->json(['ok' => true]);
    }

    /** Add one image to project gallery (sort_order auto: 1, 2, 3…). */
    public function storeImage(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);

        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $this->uploads->append(
            $workProject,
            $request->user()->id,
            Upload::COLLECTION_GALLERY,
            $request->file('image'),
            'portfolio/projects/'.$request->user()->id.'/'.$workProject->id,
        );

        return response()->json(['data' => $workProject->fresh()->load('gallery')], 201);
    }

    /** Reorder gallery: body { order: [uploadId, …] } → sort_order 1..n */
    public function reorderImages(Request $request, WorkProject $workProject): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $this->uploads->reorder($workProject, Upload::COLLECTION_GALLERY, $data['order']);

        return response()->json(['data' => $workProject->fresh()->load('gallery')]);
    }

    /** Delete one gallery image, then renumber 1..n */
    public function destroyImage(Request $request, WorkProject $workProject, Upload $upload): JsonResponse
    {
        $this->authorizeOwner($request, $workProject);

        abort_unless(
            $upload->uploadable_type === $workProject->getMorphClass()
            && (int) $upload->uploadable_id === (int) $workProject->id
            && $upload->collection === Upload::COLLECTION_GALLERY,
            404,
        );

        $upload->delete();

        $remaining = $workProject->gallery()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $this->uploads->reorder($workProject, Upload::COLLECTION_GALLERY, $remaining);

        return response()->json(['data' => $workProject->fresh()->load('gallery')]);
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
