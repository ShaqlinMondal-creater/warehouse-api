<?php

namespace App\Http\Requests\AiModels;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiModelRequest extends FormRequest
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
        $userId = $this->user()->id;

        return [
            'label' => ['required', 'string', 'max:80'],
            'provider' => ['required', 'in:openai,claude,openrouter'],
            'model_id' => [
                'required',
                'string',
                'max:160',
                Rule::unique('ai_models', 'model_id')
                    ->where(fn ($q) => $q
                        ->where('user_id', $userId)
                        ->where('provider', $this->input('provider'))),
            ],
            'api_token' => ['required', 'string', 'max:500'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
