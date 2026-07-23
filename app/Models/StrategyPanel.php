<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StrategyPanel extends Model
{
    protected $fillable = ['name', 'entry_time'];

    public function legs()
    {
        return $this->hasMany(StrategyPanelLeg::class);
    }
}
