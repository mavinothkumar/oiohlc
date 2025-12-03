<?php
// app/Models/DailyTrendMeta.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTrendMeta extends Model
{
    protected $fillable = [
        'daily_trend_id', 'tracked_date', 'recorded_at', 'sequence_id',
        'ce_ltp', 'pe_ltp', 'index_ltp',
        'market_scenario', 'triggers', 'trade_signal',
        'pdh_equiv', 'pdl_equiv', 'pdc_equiv',
        'six_levels_status', 'resistance_levels', 'support_levels',
        'call_seller_status', 'put_seller_status',
        'spot_above_pdc', 'spot_below_pdc', 'spot_break_resistance', 'spot_break_support',
        'ce_above_pdh', 'pe_above_pdh', 'ce_below_6levels', 'pe_below_6levels',
        'dominant_side', 'big_reversal_pattern', 'good_zone',
        'ce_type', 'pe_type', 'broken_status',
        'first_trigger_at', 'first_broken_at'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'ce_ltp' => 'decimal:2', 'pe_ltp' => 'decimal:2', 'index_ltp' => 'decimal:2',
        'pdh_equiv' => 'decimal:2', 'pdl_equiv' => 'decimal:2', 'pdc_equiv' => 'decimal:2',
        'triggers' => 'array',
        'six_levels_status' => 'array',
        'resistance_levels' => 'array',
        'support_levels' => 'array',
        'call_seller_status' => 'array',
        'put_seller_status' => 'array',
        'spot_above_pdc' => 'boolean',
        'spot_below_pdc' => 'boolean',
        'spot_break_resistance' => 'boolean',
        'spot_break_support' => 'boolean',
        'ce_above_pdh' => 'boolean',
        'pe_above_pdh' => 'boolean',
        'ce_below_6levels' => 'boolean',
        'pe_below_6levels' => 'boolean',
        'big_reversal_pattern' => 'boolean',
        'first_trigger_at' => 'datetime',
        'first_broken_at' => 'datetime',
    ];

    public function dailyTrend(): BelongsTo
    {
        return $this->belongsTo(DailyTrend::class);
    }
}
