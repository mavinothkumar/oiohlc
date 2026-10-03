<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioRecord extends Model
{
    public $timestamps = false;
    protected $table = 'scenarios';

    protected $fillable = [
        'position_id',
        'name',
        'underlying_change',
        'underlying_price',
        'iv_change',
        'days_change',
        'calculated_pnl',
        'calculated_delta',
        'calculated_gamma',
        'calculated_theta',
        'calculated_vega',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'underlying_change' => 'float',
        'underlying_price' => 'float',
        'iv_change' => 'float',
        'days_change' => 'integer',
        'calculated_pnl' => 'float',
        'calculated_delta' => 'float',
        'calculated_gamma' => 'float',
        'calculated_theta' => 'float',
        'calculated_vega' => 'float',
        'metadata' => 'array',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
