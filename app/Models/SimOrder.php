<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimOrder extends Model
{
    protected $fillable = [
        'position_id',
        'session_id',
        'trade_date',
        'order_type',
        'side',
        'price',
        'qty',
        'lots',
        'pnl',
        'executed_at',
    ];

    protected $casts = [
        'trade_date'  => 'date',
        'price'       => 'decimal:2',
        'pnl'         => 'decimal:2',
        'qty'         => 'integer',
        'lots'        => 'integer',
        'executed_at' => 'datetime',
    ];

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SimPosition::class, 'position_id');
    }
}
