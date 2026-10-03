<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketSnapshot extends Model
{
    public $timestamps = false;
    protected $table = 'market_snapshots';

    protected $fillable = [
        'instrument_id',
        'timestamp',
        'bid',
        'ask',
        'ltp',
        'volume',
        'open_interest',
        'iv',
        'delta',
        'gamma',
        'theta',
        'vega',
        'rho',
        'underlying_price',
        'source',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'bid' => 'float',
        'ask' => 'float',
        'ltp' => 'float',
        'iv' => 'float',
        'delta' => 'float',
        'gamma' => 'float',
        'theta' => 'float',
        'vega' => 'float',
        'rho' => 'float',
        'underlying_price' => 'float',
        'volume' => 'integer',
        'open_interest' => 'integer',
        'metadata' => 'array',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
