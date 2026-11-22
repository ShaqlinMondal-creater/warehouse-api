<?php

namespace App\Http\Requests\BidWorkspaces;

use Illuminate\Foundation\Http\FormRequest;

class StoreBidWorkspaceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'platform' => ['required', 'in:freelancer,upwork,other'],
            'currency' => ['nullable', 'string', 'max:8'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
