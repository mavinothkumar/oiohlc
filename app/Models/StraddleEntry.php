<?php
// app/Models/StraddleEntry.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StraddleEntry extends Model
{
    protected $fillable = [
        'symbol',
        'expiry_date',
        'entry_time',
        'index_at_entry',
        'atm_strike',
        'ce_symbol',
        'pe_symbol',
        'ce_strike',
        'pe_strike',
        'ce_entry_price',
        'pe_entry_price',
        'trade_date',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'entry_time'  => 'datetime',
    ];
}
