<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    use HasFactory;

    protected $table = 'entries';

    // Allow mass assignment when using Entry::create($data)[web:57][web:54]
    protected $fillable = [
        'underlying_symbol',
        'exchange',
        'expiry',
        'instrument_type',
        'strike',
        'side',
        'quantity',
        'entry_date',
        'entry_time',
        'entry_price',
    ];

    protected $casts = [
        'expiry'     => 'date',
        'entry_date' => 'date',
        'entry_time' => 'datetime:H:i',
    ];
}
