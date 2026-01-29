<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expiry extends Model
{
    protected $table = 'nse_expiries';

    protected $fillable = [
        'exchange',
        'segment',
        'expiry',
        'expiry_date',
        'instrument_type',
        'trading_symbol',
        'is_current',
        'is_next',
    ];
}

