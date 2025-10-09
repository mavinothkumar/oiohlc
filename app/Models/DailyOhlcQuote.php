<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyOhlcQuote extends Model
{
    protected $table = 'daily_ohlc_quotes';

    protected $fillable = [
        'symbol_name',
        'instrument_key',
        'expiry',
        'strike',
        'option_type',
        'quote_date',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'open_interest',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'open' => 'float',
        'high' => 'float',
        'low' => 'float',
        'close' => 'float',
        'volume' => 'integer',
        'open_interest' => 'integer',
    ];
}

