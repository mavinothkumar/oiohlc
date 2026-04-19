<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimPosition extends Model
{
    protected $fillable = [
	    'session_id',
	    'trade_date',
	    'expiry',
	    'underlying',
	    'strike',
	    'instrument_type',
	    'side',
	    'avg_entry',
	    'total_qty',
	    'open_qty',
	    'realized_pnl',
	    'status',
	    'strategy',
    ];

    protected $casts = [
        'trade_date'   => 'date',
        'expiry'       => 'date',
        'avg_entry'    => 'decimal:2',
        'realized_pnl' => 'decimal:2',
        'total_qty'    => 'integer',
        'open_qty'     => 'integer',
    ];

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SimOrder::class, 'position_id');
    }

    public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SimTradeNote::class, 'position_id');
    }
}
