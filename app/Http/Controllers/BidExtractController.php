<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesOwnedResources;
use App\Http\Requests\Bids\ExtractBidsRequest;
use App\Http\Requests\Bids\ImportBidsRequest;
use App\Models\AiModel;
use App\Models\Bid;
use App\Models\BidWorkspace;
use App\Services\BidComparison;
use App\Services\BidExtractService;
use App\Services\BidImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Throwable;

class BidExtractController extends Controller
{
    use AuthorizesOwnedResources;

    public function extract(
        ExtractBidsRequest $request,
        BidWorkspace $bidWorkspace,
        BidExtractService $extractor,
        BidComparison $comparison,
    ): JsonResponse {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $data = $request->validated();
        $aiModel = $this->resolveAiModel($request, isset($data['ai_model_id']) ? (int) $data['ai_model_id'] : null);

        try {
            /** @var UploadedFile $image */
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
            $same = $existing ? $comparison->isSame($existing, $row) : false;
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

    public function import(
        ImportBidsRequest $request,
        BidWorkspace $bidWorkspace,
        BidImportService $importer,
    ): JsonResponse {
        $this->authorizeWorkspace($request, $bidWorkspace);

        $result = $importer->import($bidWorkspace, $request->validated('bids'));

        return response()->json([
            'ok' => true,
            'added' => $result['added'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'data' => $result['data'],
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
}
