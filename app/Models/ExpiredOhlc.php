<?php

// app/Models/ExpiredOhlc.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpiredOhlc extends Model
{
    use HasFactory;

    protected $table = 'expired_ohlc';

    protected $fillable = [
        'underlying_symbol',
        'exchange',
        'expiry',
        'instrument_key',
        'instrument_type',
        'strike',
        'open',
        'high',
        'low',
        'close',
        'interval',
        'volume',
        'open_interest',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'expiry'        => 'date:Y-m-d',
            'timestamp'     => 'datetime',
            'open'          => 'decimal:2',
            'high'          => 'decimal:2',
            'low'           => 'decimal:2',
            'close'         => 'decimal:2',
            'volume'        => 'integer',
            'open_interest' => 'integer',
            'strike'        => 'integer',
        ];
    }
}
