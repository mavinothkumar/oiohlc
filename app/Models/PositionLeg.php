<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionLeg extends Model
{
    protected $table = 'position_legs';

    protected $fillable = [
        'position_id',
        'instrument_id',
        'side', // BUY, SELL
        'quantity',
        'entry_price',
        'current_price',
        'entry_timestamp',
        'exit_price',
        'exit_timestamp',
        'status', // OPEN, CLOSED
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'entry_price' => 'float',
        'current_price' => 'float',
        'exit_price' => 'float',
        'entry_timestamp' => 'datetime',
        'exit_timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'instrument_id');
    }
}
