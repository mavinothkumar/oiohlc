<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Risk Engine & Monitoring Thresholds
    |--------------------------------------------------------------------------
    |
    | Fully configurable risk rules used by PositionStateEngine and AlertService.
    |
    */
    'states' => [
        'NORMAL' => 'Position remains within configured monitoring boundaries.',
        'CAUTION' => 'Directional or volatility exposure is increasing.',
        'STRESS' => 'Gamma and directional exposure have increased significantly.',
        'RISK_REVIEW' => 'Multiple risk dimensions have moved outside original position assumptions.',
        'CLOSED' => 'Position is squared off.',
    ],

    'thresholds' => [
        'delta' => [
            'warning' => 20.0,
            'critical' => 45.0,
        ],
        'gamma' => [
            'warning' => 0.005,
            'critical' => 0.012,
        ],
        'vega' => [
            'warning' => 150.0,
            'critical' => 350.0,
        ],
        'iv_change_pct' => [
            'warning' => 15.0, // 15% IV relative spike/crush
            'critical' => 30.0,
        ],
        'breakeven_proximity_pct' => [
            // Distance from strike toward breakeven as % of BE span
            'warning' => 60.0,
            'critical' => 85.0,
        ],
        'dte' => [
            'warning' => 2,
            'critical' => 0,
        ],
        'pnl_drawdown_pct' => [
            'warning' => 10.0,
            'critical' => 25.0,
        ],
    ],
];
