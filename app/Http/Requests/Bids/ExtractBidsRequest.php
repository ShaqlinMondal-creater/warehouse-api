<?php

namespace App\Http\Requests\Bids;

use Illuminate\Foundation\Http\FormRequest;

class ExtractBidsRequest extends FormRequest
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
        return [
            'image' => ['required', 'file', 'image', 'max:8192'],
            'ai_model_id' => ['nullable', 'integer'],
        ];
    }
}
