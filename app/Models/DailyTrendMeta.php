<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTrendMeta extends Model
{
    protected $table = 'daily_trend_meta';

    protected $fillable = [
        'daily_trend_id',
        'tracked_date',
        'recorded_at',
        'ce_ltp',
        'pe_ltp',
        'index_ltp',
        'market_scenario',
        'trade_signal',
        'ce_type',
        'pe_type',
        'triggers',
        'levels_crossed',
        'broken_status',
        'first_broken_at',
        'dominant_side',
        'good_zone',
        'sequence_id',
    ];

    protected $casts = [
        'tracked_date'    => 'date',
        'recorded_at'     => 'datetime',
        'first_broken_at' => 'datetime',
        'ce_ltp'          => 'decimal:2',
        'pe_ltp'          => 'decimal:2',
        'index_ltp'       => 'decimal:2',
        'triggers'        => 'array',
        'levels_crossed'  => 'array',
    ];

    public function dailyTrend(): BelongsTo
    {
        return $this->belongsTo(DailyTrend::class, 'daily_trend_id');
    }
}
