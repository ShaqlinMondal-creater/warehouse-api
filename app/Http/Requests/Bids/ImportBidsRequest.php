<?php

namespace App\Http\Requests\Bids;

use Illuminate\Foundation\Http\FormRequest;

class ImportBidsRequest extends FormRequest
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
        ];
    }
}
