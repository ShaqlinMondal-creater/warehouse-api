<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiModelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = AiModel::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (AiModel $m) => $this->present($m))
            ->values();

        return response()->json([
            'data' => $items,
            'providers' => [
                ['id' => 'openai', 'label' => 'OpenAI', 'env_key' => 'OPENAI_API_KEY'],
                ['id' => 'claude', 'label' => 'Claude (Anthropic)', 'env_key' => 'ANTHROPIC_API_KEY'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;

        $item = DB::transaction(function () use ($request, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefaults($request->user()->id);
            }

            return AiModel::query()->create($data);
        });

        return response()->json(['data' => $this->present($item)], 201);
    }

    public function update(Request $request, AiModel $aiModel): JsonResponse
    {
        $this->authorizeOwner($request, $aiModel);
        $data = $this->validated($request, $aiModel);

        $item = DB::transaction(function () use ($request, $aiModel, $data) {
            if (array_key_exists('is_default', $data) && $data['is_default']) {
                $this->clearDefaults($request->user()->id);
            }

            $aiModel->update($data);

            return $aiModel->fresh();
        });

        return response()->json(['data' => $this->present($item)]);
    }

    public function destroy(Request $request, AiModel $aiModel): JsonResponse
    {
        $this->authorizeOwner($request, $aiModel);
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
        $this->authorizeOwner($request, $aiModel);

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

        return response()->json(['data' => $this->present($aiModel->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AiModel $existing = null): array
    {
        $userId = $request->user()->id;

        return $request->validate([
            'label' => [$existing ? 'sometimes' : 'required', 'string', 'max:80'],
            'provider' => [$existing ? 'sometimes' : 'required', 'in:openai,claude'],
            'model_id' => [
                $existing ? 'sometimes' : 'required',
                'string',
                'max:120',
                Rule::unique('ai_models', 'model_id')
                    ->where(fn ($q) => $q
                        ->where('user_id', $userId)
                        ->where('provider', $request->input('provider', $existing?->provider)))
                    ->ignore($existing?->id),
            ],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function clearDefaults(int $userId): void
    {
        AiModel::query()->where('user_id', $userId)->update(['is_default' => false]);
    }

    private function authorizeOwner(Request $request, AiModel $model): void
    {
        abort_unless($model->user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AiModel $model): array
    {
        return [
            'id' => $model->id,
            'user_id' => $model->user_id,
            'label' => $model->label,
            'provider' => $model->provider,
            'model_id' => $model->model_id,
            'is_enabled' => $model->is_enabled,
            'is_default' => $model->is_default,
            'expires_at' => $model->expires_at?->toDateString(),
            'expiry_status' => $model->expiryStatus(),
            'is_expired' => $model->isExpired(),
            'sort_order' => $model->sort_order,
            'notes' => $model->notes,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
    }
}
