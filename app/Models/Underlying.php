<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Underlying extends Model
{
    protected $table = 'underlyings';

    protected $fillable = [
        'symbol',
        'name',
        'exchange',
        'segment',
        'tick_size',
        'lot_size',
        'status',
    ];

    protected $casts = [
        'tick_size' => 'float',
        'lot_size' => 'integer',
    ];

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class, 'underlying_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'underlying_id');
    }
}
