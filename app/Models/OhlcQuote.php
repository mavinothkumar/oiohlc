<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OhlcQuote extends Model
{
    protected $table = 'ohlc_quotes';

    protected $casts = [
        'expiry_date' => 'date',
        'ts_at' => 'datetime',
    ];

    protected $fillable = [
        'instrument_key',
        'instrument_type',
        'expiry_date',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'ts',
        'ts_at',
        'last_price',
        'strike_price',
        'name',
        'trading_symbol',
    ];
}
