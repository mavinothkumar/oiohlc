<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiasSnapshot extends Model
{
    protected $fillable = [
        'trading_symbol', 'date', 'expiry_date', 'spot_price',
        'atm_strike', 'strikes_range', 'bias_score', 'bias',
        'bias_strength',

        'ce_long_build_oi',  'ce_short_build_oi',  'ce_short_cover_oi',  'ce_long_unwind_oi',
        'ce_long_build_vol', 'ce_short_build_vol', 'ce_short_cover_vol', 'ce_long_unwind_vol',

        'pe_long_build_oi',  'pe_short_build_oi',  'pe_short_cover_oi',  'pe_long_unwind_oi',
        'pe_long_build_vol', 'pe_short_build_vol', 'pe_short_cover_vol', 'pe_long_unwind_vol',

        'bullish_oi', 'bearish_oi', 'total_volume', 'captured_at',
    ];

    protected $casts = [
        'date'        => 'date',
        'captured_at' => 'datetime',
        'spot_price'  => 'float',
        'atm_strike'  => 'float',
        'bias_score'  => 'integer',
    ];
}
