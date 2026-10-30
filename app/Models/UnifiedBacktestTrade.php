<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnifiedBacktestTrade extends Model
{
    protected $table = 'unified_backtest_trades';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'entry_price' => 'float',
        'candle_high' => 'float',
        'candle_low' => 'float',
        'sl_price' => 'float',
        'risk_pts' => 'float',
        'tp1_price' => 'float',
        'tp2_price' => 'float',
        'exit_price' => 'float',
        'net_pts' => 'float',
        'pnl_pts_tp1' => 'float',
        'pnl_pts_tp2' => 'float',
        'rr_realized' => 'float',
        'mfe_pts' => 'float',
        'mae_pts' => 'float',
        'candles_held' => 'integer',
        'stock_adv_pct' => 'float',
        'stock_dec_pct' => 'float',
        'opt_bull_pct' => 'float',
        'opt_bear_pct' => 'float',
    ];
}
