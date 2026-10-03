<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyModel extends Model
{
    protected $table = 'strategies';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'version',
        'configuration',
        'status',
    ];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'strategy_id');
    }
}
