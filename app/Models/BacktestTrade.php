<?php

// app/Models/BacktestTrade.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BacktestTrade extends Model {
    protected $table = 'backtest_trades';

    protected $fillable = [
        'underlying_symbol',
        'instrument_type',
        'exchange',
        'expiry',
        'instrument_key',
        'strike',
        'entry_price',
        'exit_price',
        'side',
        'qty',
        'pnl',
        'strategy',
        'entry_time',
        'exit_time',
        'trade_time_duration',
        'outcome',
        'trade_date',
        'backtest_run_id',
        'index_price_at_entry',
        'target',
        'stoploss',
        'lot_size',
        'strike_offset',
        'signal_time',
        'ce_strike',
        'pe_strike',
        'gap_used',
        'gap_pct_prev_range',
        'previous_day_range'
    ];

    protected $casts = [
        'expiry'     => 'date',
        'trade_date' => 'date',
        'entry_time' => 'datetime',
        'exit_time'  => 'datetime',
    ];
}
