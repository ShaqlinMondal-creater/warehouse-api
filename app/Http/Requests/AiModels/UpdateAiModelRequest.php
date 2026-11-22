<?php

namespace App\Http\Requests\AiModels;

use App\Models\AiModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AiModel $existing */
        $existing = $this->route('aiModel');
        $userId = $this->user()->id;

        return [
            'label' => ['sometimes', 'string', 'max:80'],
            'provider' => ['sometimes', 'in:openai,claude,openrouter'],
            'model_id' => [
                'sometimes',
                'string',
                'max:160',
                Rule::unique('ai_models', 'model_id')
                    ->where(fn ($q) => $q
                        ->where('user_id', $userId)
                        ->where('provider', $this->input('provider', $existing->provider)))
                    ->ignore($existing->id),
            ],
            'api_token' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
