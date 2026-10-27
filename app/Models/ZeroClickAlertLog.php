<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZeroClickAlertLog extends Model
{
    use HasFactory;

    protected $table = 'zero_click_alert_logs';

    protected $fillable = [
        'triggered_at',
        'trade_date',
        'candle_time',
        'action',
        'symbol',
        'spot_price',
        'sl_price',
        'tp_price',
        'sl_offset_pts',
        'tp_offset_pts',
        'lots',
        'mode',
        'product',
        'category',
        'breadth_pct',
        'breadth_weight_pct',
        'breadth_count',
        'net_points',
        'top_contributor',
        'payload',
        'status',
        'endpoint_url',
        'api_status_code',
        'api_response',
        'latency_ms',
        'dispatched_at',
    ];

    protected $casts = [
        'triggered_at'       => 'datetime',
        'trade_date'         => 'date',
        'dispatched_at'      => 'datetime',
        'spot_price'         => 'decimal:2',
        'sl_price'           => 'decimal:2',
        'tp_price'           => 'decimal:2',
        'breadth_pct'        => 'decimal:2',
        'breadth_weight_pct' => 'decimal:2',
        'net_points'         => 'decimal:2',
        'latency_ms'         => 'decimal:2',
        'payload'            => 'array',
        'api_response'       => 'array',
    ];
}
