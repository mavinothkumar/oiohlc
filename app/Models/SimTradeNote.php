<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimTradeNote extends Model
{
    protected $fillable = [
        'position_id',
        'session_id',
        'comment',
        'outcome',
        'exit_price',
        'exit_qty',
    ];

    protected $casts = [
        'exit_price' => 'decimal:2',
        'exit_qty'   => 'integer',
    ];

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SimPosition::class, 'position_id');
    }
}
