<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Bid;
use App\Models\BidWorkspace;
use App\Services\BidExtractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BidExtractController extends Controller
{
    public function extract(Request $request, BidWorkspace $bidWorkspace, BidExtractService $extractor): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'max:8192'],
            'ai_model_id' => ['nullable', 'integer'],
        ]);

        $aiModel = $this->resolveAiModel($request, isset($data['ai_model_id']) ? (int) $data['ai_model_id'] : null);

        try {
            /** @var \Illuminate\Http\UploadedFile $image */
            $image = $data['image'];
            $bids = $extractor->extractFromImage($image, $aiModel);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'workspace_id' => $bidWorkspace->id,
            'ai_model' => $aiModel ? [
                'id' => $aiModel->id,
                'label' => $aiModel->label,
                'provider' => $aiModel->provider,
                'model_id' => $aiModel->model_id,
            ] : null,
            'extracted' => count($bids),
            'bids' => $bids,
        ]);
    }

    public function import(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $payload = $request->validate([
            'bids' => ['required', 'array', 'min:1'],
            'bids.*.project_name' => ['required', 'string', 'max:255'],
            'bids.*.total_bids' => ['nullable', 'integer', 'min:0'],
            'bids.*.average_bid' => ['nullable', 'numeric'],
            'bids.*.average_bid_raw' => ['nullable', 'string', 'max:80'],
            'bids.*.my_bid' => ['nullable', 'numeric'],
            'bids.*.my_bid_raw' => ['nullable', 'string', 'max:80'],
            'bids.*.bid_type' => ['nullable', 'in:fixed,hourly'],
            'bids.*.bid_placed_at' => ['nullable', 'date'],
            'bids.*.status' => ['nullable', 'in:active,watching,won,lost,archived'],
        ]);

        $added = 0;
        $updated = 0;
        $saved = [];

        foreach ($payload['bids'] as $row) {
            $existing = Bid::query()
                ->where('bid_workspace_id', $bidWorkspace->id)
                ->where('project_name', $row['project_name'])
                ->exists();

            $bid = Bid::query()->updateOrCreate(
                [
                    'bid_workspace_id' => $bidWorkspace->id,
                    'project_name' => $row['project_name'],
                ],
                [
                    'total_bids' => $row['total_bids'] ?? null,
                    'average_bid' => $row['average_bid'] ?? null,
                    'average_bid_raw' => $row['average_bid_raw'] ?? null,
                    'my_bid' => $row['my_bid'] ?? null,
                    'my_bid_raw' => $row['my_bid_raw'] ?? null,
                    'bid_type' => $row['bid_type'] ?? null,
                    'bid_placed_at' => $row['bid_placed_at'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'source' => 'screenshot',
                    'last_synced_at' => now(),
                ],
            );

            if ($existing) {
                $updated++;
            } else {
                $added++;
            }
            $saved[] = $bid;
        }

        return response()->json([
            'ok' => true,
            'added' => $added,
            'updated' => $updated,
            'data' => $saved,
        ]);
    }

    private function resolveAiModel(Request $request, ?int $aiModelId): ?AiModel
    {
        $query = AiModel::query()->where('user_id', $request->user()->id);

        if ($aiModelId) {
            $model = (clone $query)->whereKey($aiModelId)->first();
            abort_unless($model !== null, 404, 'AI model not found.');

            return $model;
        }

        return $query
            ->where('is_default', true)
            ->where('is_enabled', true)
            ->first()
            ?? $query->where('is_enabled', true)->orderBy('sort_order')->first();
    }

    private function authorizeWorkspace(Request $request, BidWorkspace $workspace): void
    {
        abort_unless($workspace->user_id === $request->user()->id, 404);
    }
}
