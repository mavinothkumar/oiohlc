<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StrategyPanelLeg extends Model
{
    protected $fillable = ['strategy_panel_id', 'strike_price', 'option_type', 'expiry_type', 'quantity', 'side'];

    public function panel()
    {
        return $this->belongsTo(StrategyPanel::class, 'strategy_panel_id');
    }
}
