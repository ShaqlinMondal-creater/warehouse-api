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

        $existingByName = Bid::query()
            ->where('bid_workspace_id', $bidWorkspace->id)
            ->whereIn('project_name', collect($bids)->pluck('project_name')->filter()->all())
            ->get()
            ->keyBy('project_name');

        $enriched = [];
        foreach ($bids as $row) {
            $existing = $existingByName->get($row['project_name'] ?? '');
            $same = $existing ? $this->isSameBid($existing, $row) : false;
            $enriched[] = array_merge($row, [
                'already_exists' => $existing !== null,
                'same_as_saved' => $same,
                'suggested_save' => ! $same,
            ]);
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
            'extracted' => count($enriched),
            'already_same' => collect($enriched)->where('same_as_saved', true)->count(),
            'bids' => $enriched,
        ]);
    }

    public function import(Request $request, BidWorkspace $bidWorkspace): JsonResponse
    {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $payload = $request->validate([
            'bids' => ['required', 'array', 'min:1'],
            'bids.*.project_name' => ['required', 'string', 'max:255'],
            'bids.*.total_bids' => ['nullable', 'integer', 'min:0'],
            'bids.*.my_rank' => ['nullable', 'string', 'max:20'],
            'bids.*.client_viewed' => ['nullable', 'boolean'],
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
        $skipped = 0;
        $saved = [];

        foreach ($payload['bids'] as $row) {
            $existing = Bid::query()
                ->where('bid_workspace_id', $bidWorkspace->id)
                ->where('project_name', $row['project_name'])
                ->first();

            if ($existing && $this->isSameBid($existing, $row)) {
                $skipped++;
                $saved[] = $existing;

                continue;
            }

            $bid = Bid::query()->updateOrCreate(
                [
                    'bid_workspace_id' => $bidWorkspace->id,
                    'project_name' => $row['project_name'],
                ],
                [
                    'total_bids' => $row['total_bids'] ?? null,
                    'my_rank' => $row['my_rank'] ?? null,
                    'client_viewed' => array_key_exists('client_viewed', $row)
                        ? (bool) $row['client_viewed']
                        : false,
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
            'skipped' => $skipped,
            'data' => $saved,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isSameBid(Bid $existing, array $row): bool
    {
        $sameNumber = function ($a, $b): bool {
            if ($a === null && ($b === null || $b === '')) {
                return true;
            }
            if ($a === null || $b === null || $b === '') {
                return false;
            }

            return abs((float) $a - (float) $b) < 0.001;
        };

        $sameInt = function ($a, $b): bool {
            if ($a === null && ($b === null || $b === '')) {
                return true;
            }
            if ($a === null || $b === null || $b === '') {
                return false;
            }

            return (int) $a === (int) $b;
        };

        $sameRank = function ($a, $b): bool {
            $left = $a === null || $a === '' ? null : (string) $a;
            $right = $b === null || $b === '' ? null : (string) $b;

            return $left === $right;
        };

        $incomingViewed = array_key_exists('client_viewed', $row) ? $row['client_viewed'] : false;
        $existingViewed = $existing->client_viewed ?? false;

        return $sameInt($existing->total_bids, $row['total_bids'] ?? null)
            && $sameRank($existing->my_rank, $row['my_rank'] ?? null)
            && $sameNumber($existing->my_bid, $row['my_bid'] ?? null)
            && $sameNumber($existing->average_bid, $row['average_bid'] ?? null)
            && (bool) $existingViewed === (bool) $incomingViewed;
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
