<?php

namespace App\Http\Resources;

use App\Models\FundHolding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FundHolding $resource
 */
class FundHoldingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'name' => $this->name,
            'weighting' => $this->weighting,
        ];
    }
}
