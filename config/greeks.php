<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Greeks Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Defines model types, precision, and calculation parameters for Black-Scholes
    | and Greeks aggregations.
    |
    */
    'model' => 'black_scholes',
    'trading_days_per_year' => 252,
    'calendar_days_per_year' => 365,
    'time_decay_basis' => 'calendar_days', // calendar_days or trading_days

    // Sign conventions:
    // Long Call: Delta > 0, Gamma > 0, Theta < 0, Vega > 0
    // Long Put:  Delta < 0, Gamma > 0, Theta < 0, Vega > 0
    // Short Call: Delta < 0, Gamma < 0, Theta > 0, Vega < 0
    // Short Put:  Delta > 0, Gamma < 0, Theta > 0, Vega < 0
    'normalize_by_lot' => true,
    'theta_per_day' => true, // Divide Black-Scholes annual theta by 365
    'vega_per_1_pct' => true, // Divide Black-Scholes vega by 100 for 1% IV move
    'gamma_per_1_unit' => true, // Per 1 point underlying change
];
