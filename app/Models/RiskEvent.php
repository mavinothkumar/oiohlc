<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskEvent extends Model
{
    public $timestamps = false;
    protected $table = 'risk_events';

    protected $fillable = [
        'position_id',
        'timestamp',
        'event_type',
        'severity', // INFO, WARNING, CRITICAL, EMERGENCY
        'metric',
        'previous_value',
        'current_value',
        'threshold',
        'message',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'previous_value' => 'float',
        'current_value' => 'float',
        'threshold' => 'float',
        'metadata' => 'array',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
