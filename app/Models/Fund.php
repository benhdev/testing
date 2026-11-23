<?php

namespace App\Models;

use Database\Factories\FundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    /** @use HasFactory<FundFactory> */
    use HasFactory;

    protected $fillable = [
        'sedol',
        'category',
        'risk_label',
        'market_code',
        'name',
        'sector_name',
        'currency',
        'last_price',
        'last_price_date',
        'ongoing_charge',
        'analyst_rating',
        'analyst_rating_label',
        'srri',
        'objective',
        'is_active',
        'fetched_at',
    ];

    /**
     * Route model binding uses SEDOL (e.g. /api/funds/BYW8RV9) rather than
     * the internal id — the SEDOL is the stable public identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'sedol';
    }

    protected function casts(): array
    {
        return [
            // decimal:N casts return strings so arithmetic goes through bcmath
            // and we never silently touch floats.
            'last_price' => 'decimal:4',
            'ongoing_charge' => 'decimal:4',
            'last_price_date' => 'date',
            'fetched_at' => 'datetime',
            'is_active' => 'boolean',
            'analyst_rating' => 'integer',
            'srri' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FundDocument::class);
    }

    public function holdings(): HasMany
    {
        return $this->hasMany(FundHolding::class)->orderBy('position');
    }

    public function assetAllocations(): HasMany
    {
        return $this->hasMany(FundAssetAllocation::class);
    }
}
