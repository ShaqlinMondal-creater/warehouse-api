<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesOwnedResources;
use App\Http\Requests\AiModels\StoreAiModelRequest;
use App\Http\Requests\AiModels\UpdateAiModelRequest;
use App\Http\Resources\AiModelResource;
use App\Models\AiModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiModelController extends Controller
{
    use AuthorizesOwnedResources;

    public function index(Request $request): JsonResponse
    {
        $items = AiModel::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return response()->json([
            'data' => AiModelResource::collection($items)->resolve(),
            'providers' => [
                ['id' => 'openai', 'label' => 'OpenAI', 'env_key' => 'OPENAI_API_KEY'],
                ['id' => 'claude', 'label' => 'Claude (Anthropic)', 'env_key' => 'ANTHROPIC_API_KEY'],
                ['id' => 'openrouter', 'label' => 'OpenRouter (free models OK)', 'env_key' => 'OPENROUTER_API_KEY'],
            ],
        ]);
    }

    public function store(StoreAiModelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $item = DB::transaction(function () use ($request, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefaults($request->user()->id);
            }

            return AiModel::query()->create($data);
        });

        return response()->json(['data' => (new AiModelResource($item))->resolve()], 201);
    }

    public function update(UpdateAiModelRequest $request, AiModel $aiModel): JsonResponse
    {
        $this->authorizeAiModel($request, $aiModel);
        $data = $request->validated();

        // Empty token on update = keep existing
        if (array_key_exists('api_token', $data) && (! is_string($data['api_token']) || trim($data['api_token']) === '')) {
            unset($data['api_token']);
        }

        $item = DB::transaction(function () use ($request, $aiModel, $data) {
            if (array_key_exists('is_default', $data) && $data['is_default']) {
                $this->clearDefaults($request->user()->id);
            }

            $aiModel->update($data);

            return $aiModel->fresh();
        });

        return response()->json(['data' => (new AiModelResource($item))->resolve()]);
    }

    public function destroy(Request $request, AiModel $aiModel): JsonResponse
    {
        $this->authorizeAiModel($request, $aiModel);
        $wasDefault = $aiModel->is_default;
        $userId = $aiModel->user_id;
        $aiModel->delete();

        if ($wasDefault) {
            $next = AiModel::query()
                ->where('user_id', $userId)
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function setDefault(Request $request, AiModel $aiModel): JsonResponse
    {
        $this->authorizeAiModel($request, $aiModel);

        if (! $aiModel->is_enabled) {
            return response()->json(['message' => 'Enable the model before setting it as default.'], 422);
        }

        if ($aiModel->isExpired()) {
            return response()->json(['message' => 'This model is expired. Update the expire date first.'], 422);
        }

        DB::transaction(function () use ($request, $aiModel) {
            $this->clearDefaults($request->user()->id);
            $aiModel->update(['is_default' => true]);
        });

        return response()->json(['data' => (new AiModelResource($aiModel->fresh()))->resolve()]);
    }

    private function clearDefaults(int $userId): void
    {
        AiModel::query()->where('user_id', $userId)->update(['is_default' => false]);
    }
}
