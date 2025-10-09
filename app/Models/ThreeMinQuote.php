<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreeMinQuote extends Model
{
    protected $table = 'three_min_quotes';

    protected $fillable = [
        'instrument_token',
        'symbol',
        'last_price',
        'volume',
        'average_price',
        'oi',
        'net_change',
        'total_buy_quantity',
        'total_sell_quantity',
        'lower_circuit_limit',
        'upper_circuit_limit',
        'last_trade_time',
        'oi_day_high',
        'oi_day_low',
        'open',
        'high',
        'low',
        'close',
        'timestamp',
        'diff_oi',
        'diff_volume',
        'diff_buy_quantity',
        'diff_sell_quantity',
        'diff_quantity',
        'symbol_name',
        'expiry',
        'strike',
        'option_type',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}

