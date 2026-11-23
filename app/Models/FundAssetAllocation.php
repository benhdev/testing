<?php

namespace App\Models;

use Database\Factories\FundAssetAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundAssetAllocation extends Model
{
    /** @use HasFactory<FundAssetAllocationFactory> */
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'label',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:5',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }
}
