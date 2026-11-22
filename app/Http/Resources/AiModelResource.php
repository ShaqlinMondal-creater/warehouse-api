<?php

namespace App\Http\Resources;

use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiModel */
class AiModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'label' => $this->label,
            'provider' => $this->provider,
            'model_id' => $this->model_id,
            'has_api_token' => $this->hasApiToken(),
            'api_token_masked' => $this->hasApiToken() ? '••••••••••••' : null,
            'is_enabled' => $this->is_enabled,
            'is_default' => $this->is_default,
            'expires_at' => $this->expires_at?->toDateString(),
            'expiry_status' => $this->expiryStatus(),
            'is_expired' => $this->isExpired(),
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
