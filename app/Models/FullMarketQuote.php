<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FullMarketQuote extends Model
{
    protected $table = 'full_market_quotes';

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
        'expiry',
        'strike',
        'option_type',
        'symbol_name',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}

