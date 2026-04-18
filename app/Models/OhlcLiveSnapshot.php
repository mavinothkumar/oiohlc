<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OhlcLiveSnapshot extends Model
{
    protected $table = 'ohlc_live_snapshots';

    protected $fillable = [
        'instrument_key',
        'underlying_symbol',
        'expiry_date',
        'strike',
        'instrument_type',
        'open',
        'high',
        'low',
        'close',
        'oi',
        'volume',
        'exchange',
        'interval',
        'timestamp',
        'build_up',
        'diff_oi',
        'diff_volume',
        'diff_ltp',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'timestamp'   => 'datetime',
        'open'        => 'float',
        'high'        => 'float',
        'low'         => 'float',
        'close'       => 'float',
        'diff_ltp'    => 'float',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('underlying_symbol', $symbol);
    }

    public function scopeLatestCandle($query)
    {
        return $query->orderByDesc('timestamp');
    }

    public function scopeForExpiry($query, string $expiry)
    {
        return $query->where('expiry_date', $expiry);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** CSS badge class for build-up type used in Blade views */
    public function buildUpBadgeClass(): string
    {
        return match ($this->build_up) {
            'Long Build'  => 'badge-success',
            'Short Cover' => 'badge-info',
            'Short Build' => 'badge-danger',
            'Long Unwind' => 'badge-warning',
            default       => 'badge-secondary',
        };
    }
}
