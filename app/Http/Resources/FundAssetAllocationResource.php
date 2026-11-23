<?php

namespace App\Http\Resources;

use App\Models\FundAssetAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FundAssetAllocation $resource
 */
class FundAssetAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
