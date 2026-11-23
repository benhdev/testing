<?php

namespace App\Models;

use Database\Factories\FundDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundDocument extends Model
{
    /** @use HasFactory<FundDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'type',
        'external_id',
        'url',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }
}
