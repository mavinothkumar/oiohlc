<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFlowSignalLog extends Model
{
    use HasFactory;

    protected $table = 'order_flow_signal_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'trade_date'        => 'date',
        'spot_price'        => 'double',
        'future_price'      => 'double',
        'entry_price'       => 'double',
        'stop_loss'         => 'double',
        'target_price'      => 'double',
        'opt_strike'        => 'integer',
        'opt_entry'         => 'double',
        'opt_sl'            => 'double',
        'opt_target'        => 'double',
        'confluence_score'  => 'integer',
        'obi_pct'           => 'double',
        'micro_drift'       => 'double',
        'exit_price'        => 'double',
        'pnl_points'        => 'double',
        'max_favorable_pts' => 'double',
        'max_adverse_pts'   => 'double',
    ];
}
