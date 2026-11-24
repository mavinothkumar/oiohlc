<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoTradeV2Trade extends Model
{
    protected $table = 'auto_trade_v2_trades';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'entry_spot' => 'float',
        'entry_opt_price' => 'float',
        'base_sl' => 'float',
        'current_sl' => 'float',
        't1_price' => 'float',
        't2_price' => 'float',
        't3_price' => 'float',
        't1_hit' => 'boolean',
        't2_hit' => 'boolean',
        't3_hit' => 'boolean',
        'exit_spot' => 'float',
        'exit_opt_price' => 'float',
        'pnl_points' => 'float',
        'pnl_rupees' => 'float',
        'mfe_points' => 'float',
        'mae_points' => 'float',
        'is_stagnation_trailed' => 'boolean',
        'telemetry_json' => 'array',
    ];

    public function daySummary(): BelongsTo
    {
        return $this->belongsTo(AutoTradeV2Day::class, 'date', 'date');
    }
}
