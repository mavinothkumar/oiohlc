<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionChain3M extends Model
{
    use HasFactory;

    protected $table = 'option_chains_3m';

    protected $fillable = [
        'underlying_key',
        'trading_symbol',
        'expiry',
        'strike_price',
        'option_type',
        'ltp',
        'volume',
        'oi',
        'close_price',
        'bid_price',
        'bid_qty',
        'ask_price',
        'ask_qty',
        'prev_oi',
        'vega',
        'theta',
        'gamma',
        'delta',
        'iv',
        'pop',
        'underlying_spot_price',
        'pcr',
        'captured_at',
        'build_up'
    ];
}
