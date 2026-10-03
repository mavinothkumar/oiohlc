<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $table = 'positions';

    protected $fillable = [
        'user_id',
        'strategy_id',
        'underlying_id',
        'name',
        'status', // DRAFT, OPEN, PAUSED, CLOSED, ARCHIVED
        'entry_timestamp',
        'exit_timestamp',
        'entry_underlying_price',
        'current_underlying_price',
        'entry_iv',
        'current_iv',
        'initial_credit',
        'current_value',
        'realized_pnl',
        'unrealized_pnl',
        'total_pnl',
        'metadata',
    ];

    protected $casts = [
        'entry_timestamp' => 'datetime',
        'exit_timestamp' => 'datetime',
        'entry_underlying_price' => 'float',
        'current_underlying_price' => 'float',
        'entry_iv' => 'float',
        'current_iv' => 'float',
        'initial_credit' => 'float',
        'current_value' => 'float',
        'realized_pnl' => 'float',
        'unrealized_pnl' => 'float',
        'total_pnl' => 'float',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(StrategyModel::class, 'strategy_id');
    }

    public function underlying(): BelongsTo
    {
        return $this->belongsTo(Underlying::class, 'underlying_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(PositionLeg::class, 'position_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PositionSnapshot::class, 'position_id');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(ScenarioRecord::class, 'position_id');
    }

    public function riskEvents(): HasMany
    {
        return $this->hasMany(RiskEvent::class, 'position_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AnalyticsAuditLog::class, 'position_id');
    }
}
