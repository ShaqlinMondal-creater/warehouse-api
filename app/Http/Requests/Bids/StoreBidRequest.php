<?php

namespace App\Http\Requests\Bids;

use Illuminate\Foundation\Http\FormRequest;

class StoreBidRequest extends FormRequest
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
            'project_name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:190'],
            'external_key' => ['nullable', 'string', 'max:190'],
            'total_bids' => ['nullable', 'integer', 'min:0'],
            'average_bid' => ['nullable', 'numeric'],
            'average_bid_raw' => ['nullable', 'string', 'max:80'],
            'my_bid' => ['nullable', 'numeric'],
            'my_bid_raw' => ['nullable', 'string', 'max:80'],
            'bid_type' => ['nullable', 'in:fixed,hourly'],
            'my_rank' => ['nullable', 'string', 'max:20'],
            'client_viewed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,watching,won,lost,archived'],
            'chatting' => ['nullable', 'string', 'max:80'],
            'bid_placed_at' => ['nullable', 'date'],
            'source' => ['nullable', 'in:manual,screenshot,import'],
            'notes' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
