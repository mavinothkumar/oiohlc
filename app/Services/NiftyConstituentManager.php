<?php

namespace App\Services;

class NiftyConstituentManager
{
    /**
     * Master registry of all known Nifty 50 constituent stocks with Upstox instrument keys,
     * sector classification, default beta, and full company name.
     */
    protected static array $stockMaster = [
        'HDFCBANK'   => ['key' => 'NSE_EQ|INE040A01034', 'name' => 'HDFC Bank Ltd.',                    'sector' => 'Financial Services',         'beta' => 1.25],
        'RELIANCE'   => ['key' => 'NSE_EQ|INE002A01018', 'name' => 'Reliance Industries Ltd.',          'sector' => 'Oil Gas & Consumables',      'beta' => 1.15],
        'ICICIBANK'  => ['key' => 'NSE_EQ|INE090A01021', 'name' => 'ICICI Bank Ltd.',                   'sector' => 'Financial Services',         'beta' => 1.20],
        'INFY'       => ['key' => 'NSE_EQ|INE009A01021', 'name' => 'Infosys Ltd.',                      'sector' => 'Information Technology',    'beta' => 1.10],
        'ITC'        => ['key' => 'NSE_EQ|INE154A01025', 'name' => 'ITC Ltd.',                          'sector' => 'Fast Moving Consumer Goods', 'beta' => 0.65],
        'TCS'        => ['key' => 'NSE_EQ|INE467B01029', 'name' => 'Tata Consultancy Services Ltd.',   'sector' => 'Information Technology',    'beta' => 0.85],
        'LT'         => ['key' => 'NSE_EQ|INE018A01030', 'name' => 'Larsen & Toubro Ltd.',             'sector' => 'Construction',               'beta' => 1.05],
        'BHARTIARTL' => ['key' => 'NSE_EQ|INE397D01024', 'name' => 'Bharti Airtel Ltd.',               'sector' => 'Telecommunication',          'beta' => 0.80],
        'AXISBANK'   => ['key' => 'NSE_EQ|INE238A01034', 'name' => 'Axis Bank Ltd.',                    'sector' => 'Financial Services',         'beta' => 1.30],
        'SBIN'       => ['key' => 'NSE_EQ|INE062A01020', 'name' => 'State Bank of India',               'sector' => 'Financial Services',         'beta' => 1.35],
        'KOTAKBANK'  => ['key' => 'NSE_EQ|INE237A01036', 'name' => 'Kotak Mahindra Bank Ltd.',          'sector' => 'Financial Services',         'beta' => 0.95],
        'M&M'        => ['key' => 'NSE_EQ|INE101A01026', 'name' => 'Mahindra & Mahindra Ltd.',          'sector' => 'Automobile',                 'beta' => 1.20],
        'HINDUNILVR' => ['key' => 'NSE_EQ|INE030A01027', 'name' => 'Hindustan Unilever Ltd.',          'sector' => 'Fast Moving Consumer Goods', 'beta' => 0.60],
        'MARUTI'     => ['key' => 'NSE_EQ|INE585B01010', 'name' => 'Maruti Suzuki India Ltd.',          'sector' => 'Automobile',                 'beta' => 0.90],
        'BAJFINANCE' => ['key' => 'NSE_EQ|INE296A01032', 'name' => 'Bajaj Finance Ltd.',                'sector' => 'Financial Services',         'beta' => 1.40],
        'SUNPHARMA'  => ['key' => 'NSE_EQ|INE044A01036', 'name' => 'Sun Pharmaceutical Industries Ltd.','sector' => 'Healthcare',                 'beta' => 0.70],
        'TATAMOTORS' => ['key' => 'NSE_EQ|INE155A01022', 'name' => 'Tata Motors Ltd.',                  'sector' => 'Automobile',                 'beta' => 1.45],
        'NTPC'       => ['key' => 'NSE_EQ|INE733E01010', 'name' => 'NTPC Ltd.',                         'sector' => 'Power',                      'beta' => 0.85],
        'TITAN'      => ['key' => 'NSE_EQ|INE280A01028', 'name' => 'Titan Company Ltd.',                'sector' => 'Consumer Durables',          'beta' => 0.95],
        'POWERGRID'  => ['key' => 'NSE_EQ|INE752E01010', 'name' => 'Power Grid Corp. of India Ltd.',    'sector' => 'Power',                      'beta' => 0.75],
        'TATASTEEL'  => ['key' => 'NSE_EQ|INE081A01020', 'name' => 'Tata Steel Ltd.',                   'sector' => 'Metals & Mining',            'beta' => 1.40],
        'ASIANPAINT' => ['key' => 'NSE_EQ|INE021A01026', 'name' => 'Asian Paints Ltd.',                 'sector' => 'Consumer Durables',          'beta' => 0.75],
        'ADANIENT'   => ['key' => 'NSE_EQ|INE423A01024', 'name' => 'Adani Enterprises Ltd.',            'sector' => 'Metals & Mining',            'beta' => 1.60],
        'ADANIPORTS' => ['key' => 'NSE_EQ|INE742F01042', 'name' => 'Adani Ports & SEZ Ltd.',            'sector' => 'Services',                   'beta' => 1.30],
        'COALINDIA'  => ['key' => 'NSE_EQ|INE522F01014', 'name' => 'Coal India Ltd.',                   'sector' => 'Oil Gas & Consumables',      'beta' => 0.90],
        'BAJAJFINSV' => ['key' => 'NSE_EQ|INE918I01026', 'name' => 'Bajaj Finserv Ltd.',                'sector' => 'Financial Services',         'beta' => 1.30],
        'ULTRACEMCO' => ['key' => 'NSE_EQ|INE481G01011', 'name' => 'UltraTech Cement Ltd.',             'sector' => 'Construction Materials',     'beta' => 0.95],
        'ONGC'       => ['key' => 'NSE_EQ|INE213A01029', 'name' => 'Oil & Natural Gas Corp. Ltd.',      'sector' => 'Oil Gas & Consumables',      'beta' => 1.05],
        'HCLTECH'    => ['key' => 'NSE_EQ|INE860A01027', 'name' => 'HCL Technologies Ltd.',             'sector' => 'Information Technology',    'beta' => 0.90],
        'NESTLEIND'  => ['key' => 'NSE_EQ|INE239A01024', 'name' => 'Nestle India Ltd.',                 'sector' => 'Fast Moving Consumer Goods', 'beta' => 0.55],
        'JSWSTEEL'   => ['key' => 'NSE_EQ|INE019A01038', 'name' => 'JSW Steel Ltd.',                    'sector' => 'Metals & Mining',            'beta' => 1.35],
        'GRASIM'     => ['key' => 'NSE_EQ|INE047A01021', 'name' => 'Grasim Industries Ltd.',            'sector' => 'Construction Materials',     'beta' => 1.05],
        'TECHM'      => ['key' => 'NSE_EQ|INE669C01036', 'name' => 'Tech Mahindra Ltd.',                'sector' => 'Information Technology',    'beta' => 1.15],
        'WIPRO'      => ['key' => 'NSE_EQ|INE075A01022', 'name' => 'Wipro Ltd.',                        'sector' => 'Information Technology',    'beta' => 1.05],
        'CIPLA'      => ['key' => 'NSE_EQ|INE059A01026', 'name' => 'Cipla Ltd.',                        'sector' => 'Healthcare',                 'beta' => 0.65],
        'DRREDDY'    => ['key' => 'NSE_EQ|INE089A01031', 'name' => 'Dr. Reddy\'s Laboratories Ltd.',    'sector' => 'Healthcare',                 'beta' => 0.65],
        'EICHERMOT'  => ['key' => 'NSE_EQ|INE066A01021', 'name' => 'Eicher Motors Ltd.',                'sector' => 'Automobile',                 'beta' => 0.95],
        'TATACONSUM' => ['key' => 'NSE_EQ|INE192A01025', 'name' => 'Tata Consumer Products Ltd.',       'sector' => 'Fast Moving Consumer Goods', 'beta' => 0.70],
        'BPCL'       => ['key' => 'NSE_EQ|INE029A01011', 'name' => 'Bharat Petroleum Corp. Ltd.',       'sector' => 'Oil Gas & Consumables',      'beta' => 1.10],
        'HEROMOTOCO' => ['key' => 'NSE_EQ|INE158A01026', 'name' => 'Hero MotoCorp Ltd.',                'sector' => 'Automobile',                 'beta' => 0.90],
        'BRITANNIA'  => ['key' => 'NSE_EQ|INE216A01030', 'name' => 'Britannia Industries Ltd.',         'sector' => 'Fast Moving Consumer Goods', 'beta' => 0.60],
        'HINDALCO'   => ['key' => 'NSE_EQ|INE038A01020', 'name' => 'Hindalco Industries Ltd.',          'sector' => 'Metals & Mining',            'beta' => 1.40],
        'APOLLOHOSP' => ['key' => 'NSE_EQ|INE437A01024', 'name' => 'Apollo Hospitals Enterprise Ltd.',  'sector' => 'Healthcare',                 'beta' => 0.75],
        'SBILIFE'    => ['key' => 'NSE_EQ|INE123W01016', 'name' => 'SBI Life Insurance Company Ltd.',   'sector' => 'Financial Services',         'beta' => 0.85],
        'HDFCLIFE'   => ['key' => 'NSE_EQ|INE795G01014', 'name' => 'HDFC Life Insurance Co. Ltd.',      'sector' => 'Financial Services',         'beta' => 0.80],
        'INDUSINDBK' => ['key' => 'NSE_EQ|INE095A01012', 'name' => 'IndusInd Bank Ltd.',                 'sector' => 'Financial Services',         'beta' => 1.30],
        'SHRIRAMFIN' => ['key' => 'NSE_EQ|INE721A01047', 'name' => 'Shriram Finance Ltd.',              'sector' => 'Financial Services',         'beta' => 1.30],
        'BEL'        => ['key' => 'NSE_EQ|INE263A01024', 'name' => 'Bharat Electronics Ltd.',           'sector' => 'Capital Goods',              'beta' => 1.15],
        'TRENT'      => ['key' => 'NSE_EQ|INE849A01020', 'name' => 'Trent Ltd.',                        'sector' => 'Consumer Services',          'beta' => 1.25],
        'BAJAJ-AUTO' => ['key' => 'NSE_EQ|INE917I01010', 'name' => 'Bajaj Auto Ltd.',                   'sector' => 'Automobile',                 'beta' => 0.85],
        // Historical rebalancing constituents:
        'DIVISLAB'   => ['key' => 'NSE_EQ|INE361B01024', 'name' => 'Divi\'s Laboratories Ltd.',         'sector' => 'Healthcare',                 'beta' => 0.80],
        'LTIM'       => ['key' => 'NSE_EQ|INE214T01019', 'name' => 'LTIMindtree Ltd.',                  'sector' => 'Information Technology',    'beta' => 1.15],
        'UPL'        => ['key' => 'NSE_EQ|INE628A01036', 'name' => 'UPL Ltd.',                          'sector' => 'Commodities',                'beta' => 1.20],
        'JIOFIN'     => ['key' => 'NSE_EQ|INE758E01017', 'name' => 'Jio Financial Services Ltd.',       'sector' => 'Financial Services',         'beta' => 1.10],
        'SHREECEM'   => ['key' => 'NSE_EQ|INE070A01015', 'name' => 'Shree Cement Ltd.',                 'sector' => 'Construction Materials',     'beta' => 0.90],
        'IOC'        => ['key' => 'NSE_EQ|INE242A01010', 'name' => 'Indian Oil Corp. Ltd.',             'sector' => 'Oil Gas & Consumables',      'beta' => 0.85],
    ];

    /**
     * Historical Rebalancing Periods (descending chronological order).
     * Every period has EXACTLY 50 constituents with realistic weights.
     */
    protected static array $periods = [
        [
            'name'           => 'Sep 2024 – Present',
            'effective_from' => '2024-09-30',
            'effective_to'   => '9999-12-31',
            'notes'          => 'Trent & Bharat Electronics (BEL) included; Divis Lab & LTIMindtree excluded.',
            'weights'        => [
                'HDFCBANK'   => 12.98, 'RELIANCE'   => 9.52,  'ICICIBANK'  => 7.66,  'INFY'       => 5.62,
                'ITC'        => 4.24,  'TCS'        => 4.01,  'LT'         => 3.78,  'BHARTIARTL' => 3.70,
                'AXISBANK'   => 3.32,  'SBIN'       => 2.98,  'KOTAKBANK'  => 2.64,  'M&M'        => 2.27,
                'HINDUNILVR' => 2.17,  'MARUTI'     => 1.69,  'BAJFINANCE' => 1.65,  'SUNPHARMA'  => 1.59,
                'TATAMOTORS' => 1.56,  'NTPC'       => 1.47,  'TITAN'      => 1.38,  'POWERGRID'  => 1.33,
                'TATASTEEL'  => 1.27,  'ASIANPAINT' => 1.25,  'ADANIENT'   => 1.21,  'ADANIPORTS' => 1.18,
                'COALINDIA'  => 1.14,  'BAJAJFINSV' => 1.10,  'ULTRACEMCO' => 1.05,  'ONGC'       => 1.02,
                'HCLTECH'    => 0.99,  'NESTLEIND'  => 0.92,  'JSWSTEEL'   => 0.90,  'GRASIM'     => 0.88,
                'TECHM'      => 0.83,  'WIPRO'      => 0.81,  'CIPLA'      => 0.79,  'DRREDDY'    => 0.75,
                'EICHERMOT'  => 0.74,  'TATACONSUM' => 0.72,  'BPCL'       => 0.70,  'HEROMOTOCO' => 0.67,
                'BRITANNIA'  => 0.66,  'HINDALCO'   => 0.65,  'APOLLOHOSP' => 0.63,  'SBILIFE'    => 0.61,
                'HDFCLIFE'   => 0.64,  'INDUSINDBK' => 1.05,  'SHRIRAMFIN' => 1.45,  'BEL'        => 1.35,
                'TRENT'      => 1.42,  'BAJAJ-AUTO' => 0.88,
            ]
        ],
        [
            'name'           => 'Mar 2024 – Sep 2024',
            'effective_from' => '2024-03-28',
            'effective_to'   => '2024-09-29',
            'notes'          => 'Shriram Finance included; UPL excluded; Divis Lab & LTIMindtree active.',
            'weights'        => [
                'HDFCBANK'   => 13.15, 'RELIANCE'   => 9.68,  'ICICIBANK'  => 7.78,  'INFY'       => 5.72,
                'ITC'        => 4.31,  'TCS'        => 4.08,  'LT'         => 3.85,  'BHARTIARTL' => 3.75,
                'AXISBANK'   => 3.38,  'SBIN'       => 3.03,  'KOTAKBANK'  => 2.68,  'M&M'        => 2.31,
                'HINDUNILVR' => 2.20,  'MARUTI'     => 1.72,  'BAJFINANCE' => 1.68,  'SUNPHARMA'  => 1.61,
                'TATAMOTORS' => 1.58,  'NTPC'       => 1.49,  'TITAN'      => 1.41,  'POWERGRID'  => 1.35,
                'TATASTEEL'  => 1.29,  'ASIANPAINT' => 1.27,  'ADANIENT'   => 1.23,  'ADANIPORTS' => 1.20,
                'COALINDIA'  => 1.16,  'BAJAJFINSV' => 1.12,  'ULTRACEMCO' => 1.06,  'ONGC'       => 1.03,
                'HCLTECH'    => 1.00,  'NESTLEIND'  => 0.93,  'JSWSTEEL'   => 0.91,  'GRASIM'     => 0.89,
                'TECHM'      => 0.85,  'WIPRO'      => 0.82,  'CIPLA'      => 0.80,  'DRREDDY'    => 0.77,
                'EICHERMOT'  => 0.75,  'TATACONSUM' => 0.73,  'BPCL'       => 0.71,  'HEROMOTOCO' => 0.68,
                'BRITANNIA'  => 0.67,  'HINDALCO'   => 0.66,  'APOLLOHOSP' => 0.64,  'SBILIFE'    => 0.62,
                'HDFCLIFE'   => 0.65,  'INDUSINDBK' => 1.10,  'DIVISLAB'   => 0.68,  'LTIM'       => 0.62,
                'SHRIRAMFIN' => 0.95,  'BAJAJ-AUTO' => 0.84,
            ]
        ],
        [
            'name'           => 'Jul 2023 – Mar 2024',
            'effective_from' => '2023-07-21',
            'effective_to'   => '2024-03-27',
            'notes'          => 'UPL included; LTIMindtree & Divis Lab included; Shriram Fin & Trent not yet added.',
            'weights'        => [
                'HDFCBANK'   => 13.50, 'RELIANCE'   => 10.10, 'ICICIBANK'  => 7.90,  'INFY'       => 5.85,
                'ITC'        => 4.45,  'TCS'        => 4.15,  'LT'         => 3.90,  'BHARTIARTL' => 3.65,
                'AXISBANK'   => 3.40,  'SBIN'       => 3.05,  'KOTAKBANK'  => 2.70,  'M&M'        => 2.25,
                'HINDUNILVR' => 2.25,  'MARUTI'     => 1.70,  'BAJFINANCE' => 1.65,  'SUNPHARMA'  => 1.55,
                'TATAMOTORS' => 1.55,  'NTPC'       => 1.45,  'TITAN'      => 1.40,  'POWERGRID'  => 1.30,
                'TATASTEEL'  => 1.25,  'ASIANPAINT' => 1.25,  'ADANIENT'   => 1.20,  'ADANIPORTS' => 1.15,
                'COALINDIA'  => 1.10,  'BAJAJFINSV' => 1.10,  'ULTRACEMCO' => 1.05,  'ONGC'       => 1.00,
                'HCLTECH'    => 0.98,  'NESTLEIND'  => 0.92,  'JSWSTEEL'   => 0.88,  'GRASIM'     => 0.86,
                'TECHM'      => 0.82,  'WIPRO'      => 0.80,  'CIPLA'      => 0.78,  'DRREDDY'    => 0.75,
                'EICHERMOT'  => 0.72,  'TATACONSUM' => 0.70,  'BPCL'       => 0.68,  'HEROMOTOCO' => 0.65,
                'BRITANNIA'  => 0.65,  'HINDALCO'   => 0.64,  'APOLLOHOSP' => 0.62,  'SBILIFE'    => 0.60,
                'HDFCLIFE'   => 0.66,  'INDUSINDBK' => 1.15,  'DIVISLAB'   => 0.65,  'LTIM'       => 0.60,
                'UPL'        => 0.58,  'BAJAJ-AUTO' => 0.78,
            ]
        ],
        [
            'name'           => 'Prior to Jul 2023',
            'effective_from' => '2022-09-30',
            'effective_to'   => '2023-07-20',
            'notes'          => 'Pre-HDFC merger; UPL & LTIMindtree in Nifty 50.',
            'weights'        => [
                'HDFCBANK'   => 14.10, 'RELIANCE'   => 10.40, 'ICICIBANK'  => 8.10,  'INFY'       => 6.10,
                'ITC'        => 4.60,  'TCS'        => 4.30,  'LT'         => 3.80,  'BHARTIARTL' => 3.50,
                'AXISBANK'   => 3.45,  'SBIN'       => 3.10,  'KOTAKBANK'  => 2.80,  'M&M'        => 2.15,
                'HINDUNILVR' => 2.35,  'MARUTI'     => 1.65,  'BAJFINANCE' => 1.60,  'SUNPHARMA'  => 1.50,
                'TATAMOTORS' => 1.50,  'NTPC'       => 1.40,  'TITAN'      => 1.35,  'POWERGRID'  => 1.25,
                'TATASTEEL'  => 1.20,  'ASIANPAINT' => 1.20,  'ADANIENT'   => 1.15,  'ADANIPORTS' => 1.10,
                'COALINDIA'  => 1.05,  'BAJAJFINSV' => 1.05,  'ULTRACEMCO' => 1.00,  'ONGC'       => 0.95,
                'HCLTECH'    => 0.95,  'NESTLEIND'  => 0.90,  'JSWSTEEL'   => 0.85,  'GRASIM'     => 0.82,
                'TECHM'      => 0.80,  'WIPRO'      => 0.78,  'CIPLA'      => 0.75,  'DRREDDY'    => 0.72,
                'EICHERMOT'  => 0.70,  'TATACONSUM' => 0.68,  'BPCL'       => 0.65,  'HEROMOTOCO' => 0.62,
                'BRITANNIA'  => 0.62,  'HINDALCO'   => 0.60,  'APOLLOHOSP' => 0.58,  'SBILIFE'    => 0.56,
                'HDFCLIFE'   => 0.68,  'INDUSINDBK' => 1.20,  'DIVISLAB'   => 0.65,  'LTIM'       => 0.62,
                'UPL'        => 0.60,  'BAJAJ-AUTO' => 0.75,
            ]
        ],
    ];

    /**
     * Get the active constituent configuration for any given date.
     * Guaranteed to return exactly 50 stocks with normalized weights summing to 100.0%.
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

        // If date is before earliest period, use the earliest; if after, use the latest
        if (!$selectedPeriod) {
            if ($date < self::$periods[count(self::$periods) - 1]['effective_from']) {
                $selectedPeriod = self::$periods[count(self::$periods) - 1];
            } else {
                $selectedPeriod = self::$periods[0];
            }
        }

        $rawWeights = $selectedPeriod['weights'];
        $totalWeight = array_sum($rawWeights);

        $constituents = [];
        $runningSum = 0.0;
        $keys = array_keys($rawWeights);
        $lastSym = end($keys);

        foreach ($rawWeights as $symbol => $weight) {
            $master = self::$stockMaster[$symbol] ?? [
                'key'    => "NSE_EQ|{$symbol}",
                'name'   => $symbol,
                'sector' => 'Others',
                'beta'   => 1.0,
            ];

            // Normalize so sum is exactly 100.00%
            $normWeight = $totalWeight > 0 ? round(($weight / $totalWeight) * 100, 2) : 2.0;

            if ($symbol === $lastSym) {
                // Adjust rounding residue on the last stock so sum = exactly 100.00%
                $normWeight = round(100.00 - $runningSum, 2);
            } else {
                $runningSum += $normWeight;
            }

            $constituents[] = [
                'symbol'          => $symbol,
                'name'            => $master['name'],
                'instrument_key'  => $master['key'],
                'weight'          => $normWeight,
                'sector'          => $master['sector'],
                'beta'            => $master['beta'],
                'base_price'      => 1000.0,
            ];
        }

        // Sort descending by weight
        usort($constituents, fn($a, $b) => $b['weight'] <=> $a['weight']);

        return [
            'period_name'    => $selectedPeriod['name'],
            'effective_from' => $selectedPeriod['effective_from'],
            'effective_to'   => $selectedPeriod['effective_to'],
            'notes'          => $selectedPeriod['notes'],
            'constituents'   => $constituents,
            'symbols'        => array_column($constituents, 'symbol'),
            'stock_map'      => array_combine(
                array_column($constituents, 'symbol'),
                array_column($constituents, 'instrument_key')
            ),
        ];
    }

    /**
     * Get all unique stock symbols ever used in Nifty 50 constituents across all registered periods.
     */
    public static function getAllKnownConstituentSymbols(): array
    {
        return array_keys(self::$stockMaster);
    }

    /**
     * Get mapping of symbol => Upstox instrument_key for all known constituents.
     */
    public static function getAllKnownInstruments(): array
    {
        $map = [];
        foreach (self::$stockMaster as $sym => $data) {
            $map[$sym] = $data['key'];
        }
        return $map;
    }

    /**
     * Get metadata for a specific symbol.
     */
    public static function getStockMeta(string $symbol): ?array
    {
        return self::$stockMaster[$symbol] ?? null;
    }
}
