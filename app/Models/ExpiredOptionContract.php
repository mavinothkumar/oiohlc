<?php

// app/Models/ExpiredOptionContract.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpiredOptionContract extends Model
{
    use HasFactory;

    protected $table = 'expired_option_contracts';

    protected $fillable = [
        'name',
        'segment',
        'exchange',
        'expiry',
        'instrument_key',
        'exchange_token',
        'trading_symbol',
        'tick_size',
        'lot_size',
        'instrument_type',
        'freeze_quantity',
        'underlying_key',
        'underlying_type',
        'underlying_symbol',
        'strike_price',
        'minimum_lot',
        'weekly',
        'nifty_expiry_id',
    ];

    protected function casts(): array
    {
        return [
            'expiry'          => 'date:Y-m-d',
            'tick_size'       => 'integer',
            'lot_size'        => 'integer',
            'freeze_quantity' => 'integer',
            'strike_price'    => 'integer',
            'minimum_lot'     => 'integer',
            'weekly'          => 'boolean',
        ];
    }

    // Each contract belongs to one Nifty expiry
    public function niftyExpiry()
    {
        return $this->belongsTo(ExpiredExpiry::class, 'nifty_expiry_id');
    }
}
