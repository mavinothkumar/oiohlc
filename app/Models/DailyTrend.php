<?php
// app/Models/DailyTrend.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTrend extends Model
{
    protected $table = 'daily_trend';
    protected $fillable = [
        'quote_date', 'symbol_name', 'index_high', 'index_low', 'index_close',
        'earth_value', 'earth_high', 'earth_low',
        'strike', 'ce_high', 'ce_low', 'ce_close',
        'pe_high', 'pe_low', 'pe_close',
        'min_r', 'min_s', 'max_r', 'max_s',
        'expiry_date', 'market_type', 'six_levels_broken',
        'current_day_index_open', 'market_open_time', 'ce_type', 'pe_type'
    ];

    protected $casts = [
        'quote_date' => 'date',
        'expiry_date' => 'date',
        'index_high' => 'decimal:2',
        'index_low' => 'decimal:2',
        'index_close' => 'decimal:2',
        'earth_value' => 'decimal:2',
        'earth_high' => 'decimal:2',
        'earth_low' => 'decimal:2',
        'ce_high' => 'decimal:2',
        'ce_low' => 'decimal:2',
        'ce_close' => 'decimal:2',
        'pe_high' => 'decimal:2',
        'pe_low' => 'decimal:2',
        'pe_close' => 'decimal:2',
        'min_r' => 'decimal:2',
        'min_s' => 'decimal:2',
        'max_r' => 'decimal:2',
        'max_s' => 'decimal:2',
        'current_day_index_open' => 'decimal:2',
        'market_open_time' => 'datetime',
        'six_levels_broken' => 'array',
    ];

    protected $appends = ['symbol'];

    public function getSymbolAttribute()
    {
        return $this->symbol_name;
    }
}
