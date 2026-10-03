<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instrument extends Model
{
    protected $table = 'instruments';

    protected $fillable = [
        'segment',
        'name',
        'exchange',
        'instrument_type',
        'instrument_key',
        'exchange_token',
        'trading_symbol',
        'isin',
        'short_name',
        'security_type',
        'lot_size',
        'freeze_quantity',
        'tick_size',
        'minimum_lot',
        'underlying_symbol',
        'underlying_key',
        'underlying_type',
        'expiry',
        'weekly',
        'strike_price',
        'option_type',
        'qty_multiplier',
        'mtf_enabled',
        'mtf_bracket',
        'intraday_margin',
        'intraday_leverage',
        'underlying_id',
        'symbol',
        'strike',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'strike' => 'float',
        'strike_price' => 'float',
        'lot_size' => 'integer',
        'tick_size' => 'float',
    ];

    public function underlying()
    {
        return $this->belongsTo(Underlying::class, 'underlying_id');
    }

    public function getEffectiveStrikeAttribute(): float
    {
        return (float) ($this->strike ?? $this->strike_price ?? 0);
    }
}

