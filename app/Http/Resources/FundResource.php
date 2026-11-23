<?php

namespace App\Http\Resources;

use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Fund $resource
 */
class FundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sedol' => $this->sedol,
            'name' => $this->name,
            'market_code' => $this->market_code,
            'category' => $this->category,
            'risk_label' => $this->risk_label,
            'sector_name' => $this->sector_name,
            'currency' => $this->currency,
            // decimal casts return strings so bcmath stays safe on any
            // downstream arithmetic a consumer wants to do.
            'last_price' => $this->last_price,
            'last_price_date' => $this->last_price_date?->toDateString(),
            'ongoing_charge' => $this->ongoing_charge,
            'analyst_rating' => $this->analyst_rating,
            'analyst_rating_label' => $this->analyst_rating_label,
            'srri' => $this->srri,
            'objective' => $this->objective,
            'is_active' => $this->is_active,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
            'documents' => FundDocumentResource::collection($this->whenLoaded('documents')),
            'holdings' => FundHoldingResource::collection($this->whenLoaded('holdings')),
            'asset_allocations' => FundAssetAllocationResource::collection($this->whenLoaded('assetAllocations')),
        ];
    }
}
