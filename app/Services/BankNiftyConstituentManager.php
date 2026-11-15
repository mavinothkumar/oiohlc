<?php

namespace App\Services;

class BankNiftyConstituentManager
{
    /**
     * Master registry of all known Nifty Bank constituent stocks with Upstox instrument keys,
     * sector classification, default beta, and full company name.
     */
    protected static array $stockMaster = [
        'HDFCBANK'   => ['key' => 'NSE_EQ|INE040A01034', 'name' => 'HDFC Bank Ltd.',                  'sector' => 'Private Bank', 'beta' => 1.25],
        'ICICIBANK'  => ['key' => 'NSE_EQ|INE090A01021', 'name' => 'ICICI Bank Ltd.',                 'sector' => 'Private Bank', 'beta' => 1.20],
        'SBIN'       => ['key' => 'NSE_EQ|INE062A01020', 'name' => 'State Bank of India',             'sector' => 'PSU Bank',     'beta' => 1.35],
        'AXISBANK'   => ['key' => 'NSE_EQ|INE238A01034', 'name' => 'Axis Bank Ltd.',                  'sector' => 'Private Bank', 'beta' => 1.30],
        'KOTAKBANK'  => ['key' => 'NSE_EQ|INE237A01036', 'name' => 'Kotak Mahindra Bank Ltd.',        'sector' => 'Private Bank', 'beta' => 0.95],
        'INDUSINDBK' => ['key' => 'NSE_EQ|INE095A01012', 'name' => 'IndusInd Bank Ltd.',               'sector' => 'Private Bank', 'beta' => 1.30],
        'BANKBARODA' => ['key' => 'NSE_EQ|INE028A01039', 'name' => 'Bank of Baroda',                  'sector' => 'PSU Bank',     'beta' => 1.30],
        'PNB'        => ['key' => 'NSE_EQ|INE160A01022', 'name' => 'Punjab National Bank',            'sector' => 'PSU Bank',     'beta' => 1.40],
        'AUBANK'     => ['key' => 'NSE_EQ|INE949L01017', 'name' => 'AU Small Finance Bank Ltd.',      'sector' => 'Private Bank', 'beta' => 1.10],
        'FEDERALBNK' => ['key' => 'NSE_EQ|INE171A01029', 'name' => 'Federal Bank Ltd.',               'sector' => 'Private Bank', 'beta' => 1.15],
        'IDFCFIRSTB' => ['key' => 'NSE_EQ|INE092T01019', 'name' => 'IDFC First Bank Ltd.',            'sector' => 'Private Bank', 'beta' => 1.20],
        'BANDHANBNK' => ['key' => 'NSE_EQ|INE545U01014', 'name' => 'Bandhan Bank Ltd.',               'sector' => 'Private Bank', 'beta' => 1.20],
        // Historical inclusion:
        'CANBK'      => ['key' => 'NSE_EQ|INE476A01022', 'name' => 'Canara Bank',                     'sector' => 'PSU Bank',     'beta' => 1.35],
    ];

    /**
     * Historical Rebalancing Periods (descending chronological order).
     * Every period has EXACTLY 12 constituents with realistic free-float weights.
     */
    protected static array $periods = [
        [
            'name'           => 'Present (Current)',
            'effective_from' => '2024-04-01',
            'effective_to'   => '9999-12-31',
            'weights'        => [
                'HDFCBANK'   => 29.10,
                'ICICIBANK'  => 24.20,
                'SBIN'       => 9.85,
                'AXISBANK'   => 9.40,
                'KOTAKBANK'  => 8.75,
                'INDUSINDBK' => 5.40,
                'BANKBARODA' => 2.80,
                'PNB'        => 2.15,
                'AUBANK'     => 1.85,
                'FEDERALBNK' => 1.75,
                'IDFCFIRSTB' => 1.25,
                'BANDHANBNK' => 1.00,
            ]
        ],
        [
            'name'           => 'Apr 2023 – Mar 2024',
            'effective_from' => '2023-04-01',
            'effective_to'   => '2024-03-31',
            'weights'        => [
                'HDFCBANK'   => 28.50,
                'ICICIBANK'  => 23.80,
                'SBIN'       => 10.20,
                'AXISBANK'   => 9.80,
                'KOTAKBANK'  => 9.10,
                'INDUSINDBK' => 5.80,
                'BANKBARODA' => 2.60,
                'PNB'        => 2.00,
                'AUBANK'     => 1.90,
                'FEDERALBNK' => 1.80,
                'IDFCFIRSTB' => 1.30,
                'BANDHANBNK' => 1.20,
            ]
        ],
    ];

    /**
     * Get constituents and weights for a specific date.
     */
    public static function getConstituentsForDate(string $date): array
    {
        $selectedPeriod = null;
        foreach (self::$periods as $period) {
            if ($date >= $period['effective_from'] && $date <= $period['effective_to']) {
                $selectedPeriod = $period;
                break;
            }
        }

        if (!$selectedPeriod) {
            $selectedPeriod = self::$periods[0];
        }

        $weights = $selectedPeriod['weights'];
        $constituents = [];
        $stockMap = [];

        foreach ($weights as $symbol => $weight) {
            $master = self::$stockMaster[$symbol] ?? [
                'key'    => "NSE_EQ|{$symbol}",
                'name'   => $symbol,
                'sector' => 'Private Bank',
                'beta'   => 1.0,
            ];

            $constituents[] = [
                'symbol'         => $symbol,
                'name'           => $master['name'],
                'instrument_key' => $master['key'],
                'sector'         => $master['sector'],
                'weight'         => $weight,
                'beta'           => $master['beta'],
            ];

            $stockMap[$symbol] = $master['key'];
        }

        return [
            'period_name'  => $selectedPeriod['name'],
            'constituents' => $constituents,
            'stock_map'    => $stockMap,
        ];
    }

    /**
     * Get master dictionary of all Bank Nifty stocks.
     */
    public static function getAllStocks(): array
    {
        return self::$stockMaster;
    }

    /**
     * Get default current stock map [Symbol => InstrumentKey]
     */
    public static function getCurrentStockMap(): array
    {
        $res = [];
        foreach (self::$periods[0]['weights'] as $sym => $wt) {
            if (isset(self::$stockMaster[$sym])) {
                $res[$sym] = self::$stockMaster[$sym]['key'];
            }
        }
        return $res;
    }
}
