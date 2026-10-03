<?php

return [
    'default_risk_free_rate' => 0.07, // 7% standard RBI / Indian repo rate
    'annualization_factor' => 365,
    'default_slippage_pct' => 0.001, // 0.1% slippage per leg
    'cost_per_order' => 20.0, // INR 20 brokerage flat
    'stt_sell_rate' => 0.000625, // 0.0625% on sell premium
    'exchange_turnover_rate' => 0.0005, // Exchange turnover charge
    'gst_rate' => 0.18, // 18% GST on brokerage + turnover
    'sebi_charges' => 0.000001,
    'stamp_duty_buy_rate' => 0.00003,

    'indices' => [
        'NIFTY' => [
            'name' => 'Nifty 50',
            'exchange' => 'NSE',
            'lot_size' => 65,
            'tick_size' => 0.05,
            'strike_step' => 50,
            'default_spot' => 25250,
        ],
        'BANKNIFTY' => [
            'name' => 'Nifty Bank',
            'exchange' => 'NSE',
            'lot_size' => 15,
            'tick_size' => 0.05,
            'strike_step' => 100,
            'default_spot' => 54100,
        ],
        'FINNIFTY' => [
            'name' => 'Nifty Financial Services',
            'exchange' => 'NSE',
            'lot_size' => 40,
            'tick_size' => 0.05,
            'strike_step' => 50,
            'default_spot' => 24800,
        ],
    ],
];
