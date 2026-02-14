<?php

// app/Http/Controllers/OhlcController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class OhlcChartController extends Controller
{
    public function index()
    {
        // initial page – you can preload symbols or dates if needed
        return view('options-chart');
    }

    public function expiries(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'date'              => 'required|date',
        ]);

        $symbol = $request->underlying_symbol;
        $date   = $request->date;
        [$prevDay, $spot] = $this->getPrevDayAndSpot($symbol, $date);

        // build full-day range for the selected date
        $startOfDay = $date.' 00:00:00';
        $endOfDay   = $date.' 23:59:59';

        $expiries = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->where('expiry', '>=', $startOfDay)
                      ->where('timestamp', '>=', $startOfDay)
                      ->where('timestamp', '<=', $endOfDay)
                      ->limit(1)
                      ->pluck('expiry');


        $atmStrike    = null;
        $expiryForAtm = $expiries->first();

        if ($expiryForAtm) {
            // use same date (or $prevDay) depending on how you define ATM day
            $atmStrike = $this->getAtmStrikeForDay($symbol, $expiryForAtm, $date);
        }

        return response()->json([
            'expiries'   => $expiries,
            'spot'       => $spot,
            'atm_strike' => $atmStrike,
        ]);
    }

    protected function getPrevDayAndSpot(string $symbol, string $date): array
    {
        // previous working day from nse_working_days
        $prevDay = DB::table('nse_working_days')
                     ->where('working_date', '<', $date)
                     ->orderBy('working_date', 'desc')
                     ->value('working_date');

        if ( ! $prevDay) {
            return [null, null];
        }

        // spot = previous day close of index (interval 'day')
        $spot = DB::table('expired_ohlc')
                  ->where('underlying_symbol', $symbol)
                  ->where('instrument_type', 'INDEX')
                  ->where('interval', 'day')
                  ->whereDate('timestamp', $prevDay)
                  ->value('close');

        return [$prevDay, $spot ? (float) $spot : null];
    }

    protected function getAtmStrikeForDay(string $symbol, string $expiry, string $date): ?int
    {
        return DB::table('daily_trend')
                 ->where('symbol_name', $symbol)
                 ->where('expiry_date', $expiry)
                 ->where('quote_date', $date)->value('strike');

        // all CE daily bars for that day
        $ceRows = DB::table('expired_ohlc')
                    ->where('underlying_symbol', $symbol)
                    ->whereDate('expiry', $expiry)
                    ->where('instrument_type', 'CE')
                    ->where('interval', 'day')
                    ->whereDate('timestamp', $date)
                    ->get(['strike', 'close']);

        if ($ceRows->isEmpty()) {
            return null;
        }

        // all PE daily bars for that day, keyed by strike
        $peRows = DB::table('expired_ohlc')
                    ->where('underlying_symbol', $symbol)
                    ->whereDate('expiry', $expiry)
                    ->where('instrument_type', 'PE')
                    ->where('interval', 'day')
                    ->whereDate('timestamp', $date)
                    ->get(['strike', 'close'])
                    ->keyBy('strike');

        $bestStrike = null;
        $bestDiff   = null;

        foreach ($ceRows as $ce) {
            $strike = (int) $ce->strike;
            $pe     = $peRows->get($strike);
            if ( ! $pe) {
                continue; // no matching PE for this strike
            }

            $diff = abs((float) $ce->close - (float) $pe->close);
            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff   = $diff;
                $bestStrike = $strike;
            }
        }

        return $bestStrike;
    }


    public function ohlc(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'expiry'            => 'required|date',
            'date'              => 'required|date',
            'ce_instrument_key' => 'required|string',
            'pe_instrument_key' => 'required|string',
        ]);

//        $symbol = 'NIFTY';
//        $expiry = '2025-11-25';
//        $date   = '2025-11-19';
//        $ceKey  = 26000;
//        $peKey  = 26000;

        $symbol = $request->underlying_symbol;
        $expiry = $request->expiry;
        $date   = $request->date;
        $ceKey  = $request->ce_instrument_key;
        $peKey  = $request->pe_instrument_key;

        // previous working day (for backtesting – ignore `current`/`previous` flags)
        $prevDate = DB::table('nse_working_days')
                      ->where('working_date', '<', $date)
                      ->orderBy('working_date', 'desc')
                      ->value('working_date');   // null if no earlier day

        // base for selected day
        $baseToday = DB::table('expired_ohlc')
                       ->where('underlying_symbol', $symbol)
                       ->where('expiry', $expiry)
                       ->where('interval', '5minute')
                       ->whereDate('timestamp', $date)
                       ->orderBy('timestamp', 'asc');

        $ceToday = (clone $baseToday)
            ->where('strike', $ceKey)
            ->where('instrument_type', 'CE')
            ->get(['open', 'high', 'low', 'close', 'timestamp']);

        $peToday = (clone $baseToday)
            ->where('strike', $peKey)
            ->where('instrument_type', 'PE')
            ->get(['open', 'high', 'low', 'close', 'timestamp']);
        // previous working day data
        $cePrev = collect();
        $pePrev = collect();


        if ($prevDate) {
            $basePrev = DB::table('expired_ohlc')
                          ->where('underlying_symbol', $symbol)
                          ->where('expiry', $expiry)
                          ->where('interval', '5minute')
                          ->whereDate('timestamp', $prevDate)
                          ->orderBy('timestamp', 'asc');

            $cePrev = (clone $basePrev)
                ->where('strike', $ceKey)
                ->where('instrument_type', 'CE')
                ->get(['open', 'high', 'low', 'close', 'timestamp']);

            $pePrev = (clone $basePrev)
                ->where('strike', $peKey)
                ->where('instrument_type', 'PE')
                ->get(['open', 'high', 'low', 'close', 'timestamp']);
        }


        $map = fn($row) => [
            'time'  => strtotime($row->timestamp),
            'open'  => (float) $row->open,
            'high'  => (float) $row->high,
            'low'   => (float) $row->low,
            'close' => (float) $row->close,
        ];

        // previous‑day OHLC for marking lines
        $prevOhlcCe = $cePrev->isNotEmpty()
            ? [
                'open'  => (float) $cePrev->first()->open,
                'high'  => (float) $cePrev->max('high'),
                'low'   => (float) $cePrev->min('low'),
                'close' => (float) $cePrev->last()->close,
            ]
            : null;

        $prevOhlcPe = $pePrev->isNotEmpty()
            ? [
                'open'  => (float) $pePrev->first()->open,
                'high'  => (float) $pePrev->max('high'),
                'low'   => (float) $pePrev->min('low'),
                'close' => (float) $pePrev->last()->close,
            ]
            : null;

        return response()->json([
            'prev_date'    => $prevDate,
            'ce_today'     => $ceToday->map($map)->values(),
            'pe_today'     => $peToday->map($map)->values(),
            'ce_prev'      => $cePrev->map($map)->values(),
            'pe_prev'      => $pePrev->map($map)->values(),
            'ce_prev_ohlc' => $prevOhlcCe,
            'pe_prev_ohlc' => $prevOhlcPe,
        ]);
    }

    public function multiIndex2(Request $request)
    {
        // allow empty filters (empty page with just filters)
        $request->validate([
            'symbol'      => 'nullable|string',
            'quote_date'  => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'ce_strikes'  => 'nullable|array',
            'pe_strikes'  => 'nullable|array',
        ]);

        $symbol     = $request->input('symbol');
        $quoteDate  = $request->input('quote_date');
        $expiryDate = $request->input('expiry_date');

        $trend        = null;
        $atmIndexOpen = null;
        $baseStrikes  = [];
        $ceStrikes    = [];
        $peStrikes    = [];
        $avgAtm       = null;
        $avgAll       = null;

        if ($symbol && $quoteDate && $expiryDate) {
            $trend = DB::table('daily_trend')
                       ->where('symbol_name', $symbol)
                       ->where('quote_date', $quoteDate)
                       ->where('expiry_date', $expiryDate)
                       ->first();

            if ($trend) {
                $atmIndexOpen = (float) $trend->atm_index_open;
                $step         = 50;

                // default ±2 strikes around atm_index_open
                $baseStrikes = [
                    $atmIndexOpen - 2 * $step,
                    $atmIndexOpen - 1 * $step,
                    $atmIndexOpen,
                    $atmIndexOpen + 1 * $step,
                    $atmIndexOpen + 2 * $step,
                ];

                // apply your validation / override logic
                $ceStrikes = $request->has('ce_strikes')
                    ? array_map(
                        'floatval',
                        array_filter(
                            $request->input('ce_strikes'),
                            fn($v) => $v !== null && $v !== ''
                        )
                    )
                    : $baseStrikes;

                $peStrikes = $request->has('pe_strikes')
                    ? array_map(
                        'floatval',
                        array_filter(
                            $request->input('pe_strikes'),
                            fn($v) => $v !== null && $v !== ''
                        )
                    )
                    : $baseStrikes;

                // if user partially cleared all inputs, fall back to defaults
                if (empty($ceStrikes)) {
                    $ceStrikes = $baseStrikes;
                }
                if (empty($peStrikes)) {
                    $peStrikes = $baseStrikes;
                }

                $avgAtm = ((float) $trend->atm_ce_close + (float) $trend->atm_pe_close) / 2;
                $avgAll = ((float) $trend->ce_close + (float) $trend->pe_close) / 2;
            }
        }

        return view('options-chart-multi', [
            'symbol'       => $symbol,
            'quoteDate'    => $quoteDate,
            'expiryDate'   => $expiryDate,
            'atmIndexOpen' => $atmIndexOpen,
            'ceStrikes'    => $ceStrikes,
            'peStrikes'    => $peStrikes,
            'avgAtm'       => $avgAtm,
            'avgAll'       => $avgAll,
        ]);
    }

    public function multiExpiries(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'date'              => 'required|date',
        ]);

        $symbol = $request->underlying_symbol;
        $date   = $request->date;
        [$prevDay, $spot] = $this->getPrevDayAndSpot($symbol, $date);

        // build full-day range for the selected date
        $startOfDay = $date.' 00:00:00';
        $endOfDay   = $date.' 23:59:59';

//        $expiries = DB::table('expired_ohlc')
//                      ->where('underlying_symbol', $symbol)
//                      ->where('expiry', '>=', $startOfDay)
//                      ->where('timestamp', '>=', $startOfDay)
//                      ->where('timestamp', '<=', $endOfDay)
//                      ->limit(1)
//                      ->pluck('expiry');

        $expiryForAtm = DB::table('expired_expiries')
                      ->where('underlying_symbol', $symbol)
                      ->where('instrument_type', 'OPT')
                      ->where('expiry_date', '>=', $date)
                      ->orderBy('expiry_date')
                      ->value('expiry_date');

        $atmStrike    = null;
        if ($expiryForAtm) {
            // use same date (or $prevDay) depending on how you define ATM day
            $atmStrike = $this->getAtmStrikeForDay($symbol, $expiryForAtm, $date);
        }

        $trend = DB::table('daily_trend')->where('symbol_name', $request->underlying_symbol)->where('quote_date', $request->date)->first();

        return response()->json([
            'expiries'        => [$expiryForAtm],
            'spot'            => $spot,
            'atm_strike'      => $atmStrike,
            'open_atm_strike' => $trend->atm_index_open,
        ]);


    }

    public function multiIndex(Request $request)
    {
        // allow empty filters (empty page with just filters)
        $request->validate([
            'symbol'      => 'nullable|string',
            'quote_date'  => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'ce_strikes'  => 'nullable|array',
            'pe_strikes'  => 'nullable|array',
        ]);

        $symbol     = $request->input('symbol');
        $quoteDate  = $request->input('quote_date');
        $expiryDate = $request->input('expiry_date');

        $trend            = null;
        $atmIndexOpen     = null;
        $baseStrikes      = [];
        $ceStrikes        = [];
        $peStrikes        = [];
        $avgAtm           = null;
        $avgAll           = null;
        $prevMidPoints = [];

        // new data for right‑side table
        $saturation = (float) $request->input('saturation', 5);   // adjustable +/- X
        $diffMatrix = [];   // [time => [strike => ['diff' => float, 'ce_high' => ..., ...]]]
        $timeSlots  = [];   // ordered unique 5‑minute times (Carbon strings)
        $allStrikes = [];   // merged CE + PE strikes for header

        if ($symbol && $quoteDate && $expiryDate) {
            $getExpiryFromDate = DB::table('expired_expiries')
                                   ->where('underlying_symbol', $symbol)
                                   ->where('instrument_type', 'OPT')
                                   ->where('expiry_date', '<', $expiryDate)
                                   ->orderByDesc('expiry_date')->value('expiry_date');

            $trends        = DB::table('daily_trend')
                               ->where('symbol_name', $symbol)
                               ->whereBetween('quote_date', [$getExpiryFromDate, $quoteDate])
                               ->where('expiry_date', $expiryDate)->orderByDesc('id')
                               ->get();

            foreach ($trends as $index => $_trend) {
                if (0 === $index) {
                    $trend = $_trend;
                } else {
                    $prevMidPoints[] = $_trend->mid_point;
                }
            }


            if ($trend) {
                $atmIndexOpen = (float) $trend->atm_index_open;
                $step         = 50;

                $snipperPoint = $trend->mid_point;


                $snipperSaturation = (float) $request->input('snipper_saturation', 10);

                // default ±2 strikes around atm_index_open
                $baseStrikes = [
                    $atmIndexOpen - 3 * $step,
                    $atmIndexOpen - 2 * $step,
                    $atmIndexOpen - 1 * $step,
                    $atmIndexOpen,
                    $atmIndexOpen + 1 * $step,
                    $atmIndexOpen + 2 * $step,
                    $atmIndexOpen + 3 * $step,
                ];

                // apply your validation / override logic
                $ceStrikes = $request->has('ce_strikes')
                    ? array_map(
                        'floatval',
                        array_filter(
                            $request->input('ce_strikes'),
                            fn($v) => $v !== null && $v !== ''
                        )
                    )
                    : $baseStrikes;

                $peStrikes = $request->has('pe_strikes')
                    ? array_map(
                        'floatval',
                        array_filter(
                            $request->input('pe_strikes'),
                            fn($v) => $v !== null && $v !== ''
                        )
                    )
                    : $baseStrikes;

                // if user partially cleared all inputs, fall back to defaults
                if (empty($ceStrikes)) {
                    $ceStrikes = $baseStrikes;
                }
                if (empty($peStrikes)) {
                    $peStrikes = $baseStrikes;
                }

                $avgAtm = ((float) $trend->atm_ce_close + (float) $trend->atm_pe_close) / 2;
                $avgAll = (float) $snipperPoint;

                /**
                 * NEW LOGIC:
                 * For each common strike in CE + PE, and for each 5‑minute candle,
                 * compute:
                 *   diff1 = CE_high - PE_low
                 *   diff2 = PE_high - CE_low
                 * Keep the smallest absolute diff that is within +/- saturation.
                 */
                $allStrikes = array_values(array_unique(array_merge($ceStrikes, $peStrikes)));
                sort($allStrikes);

                if ( ! empty($allStrikes)) {
                    $base = DB::table('expired_ohlc')
                              ->where('underlying_symbol', $symbol)
                              ->where('expiry', $expiryDate)
                              ->where('interval', '5minute')
                              ->whereDate('timestamp', $quoteDate)
                              ->orderBy('timestamp', 'asc');

                    // pull all CE rows for relevant strikes
                    $ceRows = (clone $base)
                        ->where('instrument_type', 'CE')
                        ->whereIn('strike', $allStrikes)
                        ->get(['strike', 'open', 'high', 'low', 'close', 'timestamp']);

                    // pull all PE rows for relevant strikes
                    $peRows = (clone $base)
                        ->where('instrument_type', 'PE')
                        ->whereIn('strike', $allStrikes)
                        ->get(['strike', 'open', 'high', 'low', 'close', 'timestamp']);

                    // group by [time => [strike => row]]
                    $ceGrouped = [];
                    foreach ($ceRows as $row) {
                        $timeKey                                   = \Carbon\Carbon::parse($row->timestamp)->format('H:i');
                        $ceGrouped[$timeKey][(float) $row->strike] = $row;
                    }

                    $peGrouped = [];
                    foreach ($peRows as $row) {
                        $timeKey                                   = \Carbon\Carbon::parse($row->timestamp)->format('H:i');
                        $peGrouped[$timeKey][(float) $row->strike] = $row;
                    }

                    // we care only about trading time window 09:15–15:30
                    $start = \Carbon\Carbon::parse($quoteDate.' 09:15:00');
                    $end   = \Carbon\Carbon::parse($quoteDate.' 15:30:00');

                    $cursor = $start->copy();
                    while ($cursor <= $end) {
                        $timeKey     = $cursor->format('H:i');
                        $timeSlots[] = $timeKey;

                        foreach ($allStrikes as $strike) {
                            $strike = (float) $strike;

                            $ce = $ceGrouped[$timeKey][$strike] ?? null;
                            $pe = $peGrouped[$timeKey][$strike] ?? null;

                            if ( ! $ce || ! $pe) {
                                continue;
                            }

                            // CE OHLC
                            $ceOpen  = (float) $ce->open;
                            $ceHigh  = (float) $ce->high;
                            $ceLow   = (float) $ce->low;
                            $ceClose = (float) $ce->close;

                            // PE OHLC
                            $peOpen  = (float) $pe->open;
                            $peHigh  = (float) $pe->high;
                            $peLow   = (float) $pe->low;
                            $peClose = (float) $pe->close;

                            // Candle color
                            $ceSide = $ceClose >= $ceOpen ? 'green' : 'red';
                            $peSide = $peClose >= $peOpen ? 'green' : 'red';

                            $candidates = [];

                            /**
                             * CASE 1: CE open > PE open  (CE is "higher")
                             *
                             * Your rules:
                             *  2. If CE higher and CE green:
                             *     - use CE Low & Open
                             *     - for PE use Open & High
                             *     (we will form several combinations from those legs)
                             *  3. If CE higher and CE red:
                             *     - use CE Close & Low
                             *     - for PE use High & Close
                             */

                            if ($ceOpen > $peOpen) {
                                if ($ceSide === 'green') {
                                    // CE higher, CE green
                                    // Build all combinations you care about:
                                    $combos = [
                                        // CE L vs PE O
                                        [
                                            'left_val'  => $ceLow,
                                            'right_val' => $peHigh,
                                            'left_src'  => 'C-L',   // CE low
                                            'right_src' => 'P-H',   // PE open
                                            'direction' => 'PE_SELL',
                                        ],
                                        [
                                            'left_val'  => $ceLow,
                                            'right_val' => $peLow,
                                            'left_src'  => 'C-L',   // CE low
                                            'right_src' => 'P-L',   // PE open
                                            'direction' => 'PE_SELL',
                                        ],
                                    ];
                                } else {
                                    $combos = [
                                        [
                                            'left_val'  => $ceLow,
                                            'right_val' => $peHigh,
                                            'left_src'  => 'C-L',   // CE close
                                            'right_src' => 'P-H',   // PE high
                                            'direction' => 'CE_SELL',
                                        ],
                                        [
                                            'left_val'  => $ceLow,
                                            'right_val' => $peLow,
                                            'left_src'  => 'C-L',   // CE close
                                            'right_src' => 'P-L',   // PE high
                                            'direction' => 'CE_SELL',
                                        ],
//
                                    ];
                                }

                                // evaluate all CE‑higher combos
                                foreach ($combos as $c) {
                                    $diff = $c['left_val'] - $c['right_val'];

                                    if (abs($diff) > $saturation) {
                                        continue;
                                    }

                                    $candidates[] = [
                                        'diff'      => $diff,
                                        'left_val'  => $c['left_val'],
                                        'right_val' => $c['right_val'],
                                        'left_src'  => $c['left_src'],
                                        'right_src' => $c['right_src'],
                                        'direction' => $c['direction'],   // CE_SELL

                                        'ce_open'  => $ceOpen,
                                        'ce_close' => $ceClose,
                                        'ce_high'  => $ceHigh,
                                        'ce_low'   => $ceLow,
                                        'pe_open'  => $peOpen,
                                        'pe_close' => $peClose,
                                        'pe_high'  => $peHigh,
                                        'pe_low'   => $peLow,
                                        'ce_side'  => $ceSide,
                                        'pe_side'  => $peSide,
                                    ];
                                }
                            }

                            /**
                             * CASE 2: PE open > CE open  (PE is "higher")
                             *
                             * "Vice versa for PE":
                             *  - PE higher & PE green: use PE Low & Open, CE Close & High
                             *  - PE higher & PE red:   use PE Close & Low, CE High & Close
                             */

                            if ($peOpen > $ceOpen) {
                                if ($peSide === 'green') {
                                    $combos = [
                                        [
                                            'left_val'  => $ceHigh,
                                            'right_val' => $peLow,
                                            'left_src'  => 'C-H',   // CE close
                                            'right_src' => 'P-L',   // PE high
                                            'direction' => 'CE_SELL',
                                        ],
                                        [
                                            'left_val'  => $ceLow,
                                            'right_val' => $peLow,
                                            'left_src'  => 'C-L',   // CE close
                                            'right_src' => 'P-L',   // PE high
                                            'direction' => 'CE_SELL',
                                        ],
//
                                    ];
                                } else {
                                    $combos = [
                                        // PE C vs CE H
                                        [
                                            'left_val'  => $ceHigh,
                                            'right_val' => $peLow,
                                            'left_src'  => 'C-H',   // CE close
                                            'right_src' => 'P-L',   // PE high
                                            'direction' => 'PE_SELL',
                                        ], [
                                            'left_val'  => $ceLow,
                                            'right_val' => $peLow,
                                            'left_src'  => 'C-L',   // CE close
                                            'right_src' => 'P-L',   // PE high
                                            'direction' => 'PE_SELL',
                                        ],
//
                                    ];
                                }

                                // evaluate all PE‑higher combos
                                foreach ($combos as $c) {
                                    $leftVal  = $c['left_val'];   // e.g. 71.9
                                    $rightVal = $c['right_val'];  // e.g. 72.2
                                    $diff     = $leftVal - $rightVal;

// main saturation filter
                                    if (abs($diff) > $saturation) {
                                        continue;
                                    }

// snipper distances from snipperPoint (94.83)
                                    $leftValSnipper  = $leftVal - $snipperPoint;
                                    $rightValSnipper = $rightVal - $snipperPoint;

// require at least ONE side to be within snipper band
                                    if (
                                        abs($leftValSnipper) > $snipperSaturation &&
                                        abs($rightValSnipper) > $snipperSaturation
                                    ) {
                                        // both far from snipperPoint -> skip cell entirely
                                        continue;
                                    }

                                    $candidates[] = [
                                        'diff'      => $diff,
                                        'left_val'  => $c['left_val'],
                                        'right_val' => $c['right_val'],
                                        'left_src'  => $c['left_src'],
                                        'right_src' => $c['right_src'],
                                        'direction' => $c['direction'],   // PE_SELL

                                        'left_snipper'  => $leftValSnipper,
                                        'right_snipper' => $rightValSnipper,
                                        'snipper_point' => $snipperPoint,
                                        'snipper_sat'   => $snipperSaturation,

                                        'ce_open'  => $ceOpen,
                                        'ce_close' => $ceClose,
                                        'ce_high'  => $ceHigh,
                                        'ce_low'   => $ceLow,
                                        'pe_open'  => $peOpen,
                                        'pe_close' => $peClose,
                                        'pe_high'  => $peHigh,
                                        'pe_low'   => $peLow,
                                        'ce_side'  => $ceSide,
                                        'pe_side'  => $peSide,
                                    ];
                                }
                            }

                            // pick best candidate (lowest abs diff) for this time & strike
                            if ( ! empty($candidates)) {
                                usort($candidates, fn($a, $b) => abs($a['diff']) <=> abs($b['diff']));
                                $best = $candidates[0];

                                // MAIN + SNIPPER FILTER (runs for ALL branches)
                                $leftVal  = $best['left_val'];
                                $rightVal = $best['right_val'];

                                $leftValSnipper  = $leftVal - $snipperPoint;
                                $rightValSnipper = $rightVal - $snipperPoint;

                                if (
                                    abs($best['diff']) <= $saturation &&
                                    (
                                        abs($leftValSnipper) <= $snipperSaturation ||
                                        abs($rightValSnipper) <= $snipperSaturation
                                    )
                                ) {
                                    // only cells passing both conditions are stored
                                    $best['left_snipper']  = $leftValSnipper;
                                    $best['right_snipper'] = $rightValSnipper;
                                    $best['snipper_point'] = $snipperPoint;
                                    $best['snipper_sat']   = $snipperSaturation;

                                    $diffMatrix[$timeKey][$strike] = $best;
                                }
                            }
                        }


                        $cursor->addMinutes(5);
                    }

                    // ensure unique ordered time slots
                    $timeSlots = array_values(array_unique($timeSlots));
                }
            }
        }

        return view('options-chart-multi', [
            'symbol'            => $symbol,
            'quoteDate'         => $quoteDate,
            'expiryDate'        => $expiryDate,
            'atmIndexOpen'      => $atmIndexOpen,
            'ceStrikes'         => $ceStrikes,
            'peStrikes'         => $peStrikes,
            'avgAtm'            => $avgAtm,
            'avgAll'            => $avgAll,
            'prevMidPoints'     => $prevMidPoints,

            // new variables for right‑side 30% panel
            'saturation'        => $saturation,
            'snipperSaturation' => $snipperSaturation ?? null,
            'diffMatrix'        => $diffMatrix,
            'timeSlots'         => $timeSlots,
            'allStrikes'        => $allStrikes,
        ]);
    }


}
