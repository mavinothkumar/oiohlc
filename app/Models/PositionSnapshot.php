<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionSnapshot extends Model
{
    public $timestamps = false;
    protected $table = 'position_snapshots';

    protected $fillable = [
        'position_id',
        'timestamp',
        'underlying_price',
        'total_value',
        'unrealized_pnl',
        'realized_pnl',
        'total_pnl',
        'delta',
        'gamma',
        'theta',
        'vega',
        'rho',
        'iv',
        'distance_from_strike',
        'distance_to_upper_breakeven',
        'distance_to_lower_breakeven',
        'expected_move',
        'regime',
        'risk_state',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'underlying_price' => 'float',
        'total_value' => 'float',
        'unrealized_pnl' => 'float',
        'realized_pnl' => 'float',
        'total_pnl' => 'float',
        'delta' => 'float',
        'gamma' => 'float',
        'theta' => 'float',
        'vega' => 'float',
        'rho' => 'float',
        'iv' => 'float',
        'distance_from_strike' => 'float',
        'distance_to_upper_breakeven' => 'float',
        'distance_to_lower_breakeven' => 'float',
        'expected_move' => 'float',
        'metadata' => 'array',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
