<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFlowIntelligenceSnapshot extends Model
{
    use HasFactory;

    protected $table = 'order_flow_intelligence_snapshots';

    protected $fillable = [
        'trade_date',
        'snapshot_time',
        'symbol',
        'instrument',
        'ltp',
        'index_ltp',
        'conviction_score',
        'regime',
        'bias',
        'dominance_ratio',
        'net_volume',
        'total_ask_volume',
        'total_bid_volume',
        'td_sup',
        'td_sup_qty',
        'td_res',
        'td_res_qty',
        'yd_sup',
        'yd_res',
        'target1',
        'target2',
        'concrete_sl',
        'trade_status',
        'whale_status',
        'fomo_status',
        'past_story',
        'present_story',
        'future_story',
        'playbook_suggestion',
        'meta',
    ];

    protected $casts = [
        'trade_date'        => 'date',
        'ltp'               => 'decimal:2',
        'index_ltp'         => 'decimal:2',
        'conviction_score'  => 'integer',
        'dominance_ratio'   => 'decimal:2',
        'net_volume'        => 'integer',
        'total_ask_volume'  => 'integer',
        'total_bid_volume'  => 'integer',
        'td_sup'            => 'decimal:2',
        'td_sup_qty'        => 'integer',
        'td_res'            => 'decimal:2',
        'td_res_qty'        => 'integer',
        'yd_sup'            => 'decimal:2',
        'yd_res'            => 'decimal:2',
        'target1'           => 'decimal:2',
        'target2'           => 'decimal:2',
        'concrete_sl'       => 'decimal:2',
        'meta'              => 'array',
    ];

    public function scopeForDate($query, $date)
    {
        return $query->where('trade_date', $date);
    }

    public function scopeForSymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeBullish($query)
    {
        return $query->where('bias', 'BUY');
    }

    public function scopeBearish($query)
    {
        return $query->where('bias', 'SELL');
    }
}
