<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFlowTradedVolume extends Model
{
    use HasFactory;

    protected $table = 'order_flow_traded_volumes';

    protected $fillable = [
        'trade_date',
        'trade_time',
        'symbol',
        'instrument',
        'side',
        'price',
        'quantity',
        'source',
        'meta',
    ];

    protected $casts = [
        'trade_date' => 'date',
        'price'      => 'decimal:2',
        'quantity'   => 'integer',
        'meta'       => 'array',
    ];

    /**
     * Scope to filter by date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('trade_date', $date);
    }

    /**
     * Scope to filter by symbol.
     */
    public function scopeForSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Scope for Ask prints (Aggressive Buys).
     */
    public function scopeAsk($query)
    {
        return $query->where('side', 'ASK');
    }

    /**
     * Scope for Bid prints (Aggressive Sells).
     */
    public function scopeBid($query)
    {
        return $query->where('side', 'BID');
    }
}
