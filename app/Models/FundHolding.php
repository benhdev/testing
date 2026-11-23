<?php

namespace App\Models;

use Database\Factories\FundHoldingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundHolding extends Model
{
    /** @use HasFactory<FundHoldingFactory> */
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'position',
        'name',
        'weighting',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'weighting' => 'decimal:5',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }
}
