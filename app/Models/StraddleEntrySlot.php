<?php
// app/Models/StraddleEntrySlot.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StraddleEntrySlot extends Model
{
    protected $fillable = [
        'symbol',
        'expiry_date',
        'hour_slot',
        'slot_time',
        'atm_strike',
        'ce_strike',
        'pe_strike',
        'ce_entry_price',
        'pe_entry_price',
        'ce_close_price',
        'pe_close_price',
        'ce_pnl',
        'pe_pnl',
        'total_pnl',
        'trade_date',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'slot_time'   => 'datetime',
    ];
}
