<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoTradeV2Day extends Model
{
    protected $table = 'auto_trade_v2_days';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'nifty_open' => 'float',
        'nifty_high' => 'float',
        'nifty_low' => 'float',
        'nifty_close' => 'float',
        'total_trades' => 'integer',
        'win_trades' => 'integer',
        'loss_trades' => 'integer',
        'net_points' => 'float',
        'net_pnl_rupees' => 'float',
        'mfe_points' => 'float',
        'mae_points' => 'float',
        'stagnation_exits_count' => 'integer',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(AutoTradeV2Trade::class, 'date', 'date');
    }
}
