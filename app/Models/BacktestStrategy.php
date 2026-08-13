<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacktestStrategy extends Model
{
    protected $table = 'backtest_strategies';

    protected $fillable = [
        'slug',
        'name',
        'version',
        'definition',
        'parameters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'parameters' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BacktestRun::class, 'strategy_id');
    }

    public function legs()
    {
        return $this->hasMany(BacktestStrategyLeg::class);
    }
}
