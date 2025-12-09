<?php

// app/Models/NiftyExpiry.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpiredExpiry extends Model
{
    use HasFactory;

    protected $table = 'expired_expiries';

    protected $fillable = [
        'underlying_instrument_key',
        'underlying_symbol',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date:Y-m-d', // Carbon date
        ];
    }

    // One expiry has many option contracts
    public function expiredOptionContracts()
    {
        return $this->hasMany(ExpiredOptionContract::class, 'nifty_expiry_id');
    }
}

