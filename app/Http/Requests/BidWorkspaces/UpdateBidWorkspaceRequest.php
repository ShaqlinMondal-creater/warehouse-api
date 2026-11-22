<?php

namespace App\Http\Requests\BidWorkspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBidWorkspaceRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'platform' => ['sometimes', 'in:freelancer,upwork,other'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
