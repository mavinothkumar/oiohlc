<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GammaBlastAlertLog extends Model
{
    use HasFactory;

    protected $table = 'gamma_blast_alert_logs';

    protected $fillable = [
        'trade_date',
        'alert_time',
        'symbol',
        'expiry',
        'spot_price',
        'alert_type',
        'title',
        'strike',
        'option_type',
        'direction',
        'score',
        'distance_pts',
        'trigger_condition',
        'zero_gamma_flip',
        'call_wall',
        'put_wall',
        'net_gex_cr',
        'regime',
        'message',
        'settings_snapshot',
        'meta',
    ];

    protected $casts = [
        'trade_date'        => 'date:Y-m-d',
        'spot_price'        => 'decimal:2',
        'strike'            => 'decimal:2',
        'score'             => 'decimal:2',
        'distance_pts'      => 'decimal:2',
        'zero_gamma_flip'   => 'decimal:2',
        'call_wall'         => 'decimal:2',
        'put_wall'          => 'decimal:2',
        'net_gex_cr'        => 'decimal:2',
        'settings_snapshot' => 'array',
        'meta'              => 'array',
    ];
}
