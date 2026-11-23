<?php

namespace App\Http\Resources;

use App\Models\FundDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FundDocument $resource
 */
class FundDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'external_id' => $this->external_id,
            'url' => $this->url,
        ];
    }
}
