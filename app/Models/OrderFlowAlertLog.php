<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFlowAlertLog extends Model
{
    use HasFactory;

    protected $table = 'order_flow_alert_logs';

    protected $fillable = [
        'mode',
        'trade_date',
        'alert_time',
        'symbol',
        'instrument',
        'alert_type',
        'side',
        'price',
        'quantity',
        'level',
        'confluence_score',
        'title',
        'details',
        'guidance',
        'sound',
        'meta',
    ];

    protected $casts = [
        'trade_date'       => 'date',
        'price'            => 'decimal:2',
        'quantity'         => 'integer',
        'level'            => 'integer',
        'confluence_score' => 'integer',
        'meta'             => 'array',
    ];
}
