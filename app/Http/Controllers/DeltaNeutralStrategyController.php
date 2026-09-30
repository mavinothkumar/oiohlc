<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class DeltaNeutralStrategyController extends Controller
{
    public function index(Request $request)
    {
        $payload = $this->buildPayload($request);

        return view('delta-neutral.index', $payload);
    }

    public function getData(Request $request)
    {
        $payload = $this->buildPayload($request);

        return response()->json([
            'success' => true,
            'data'    => $payload,
        ]);
    }

    public function getWsUrl()
    {
        $token = config('services.upstox.analytics_token');
        if (!$token) {
            return response()->json(['error' => 'Upstox access token not configured in services.upstox.analytics_token'], 400);
        }

        try {
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://api.upstox.com/v3/feed/market-data-feed/authorize');

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error'   => 'Failed to fetch WS URL from Upstox',
                'details' => $response->body()
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Exception fetching WS URL',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function fetchIndiaVix(): array
    {
        $token = config('services.upstox.analytics_token');
        $defaultVix = [
            'value'       => 13.37,
            'open'        => 13.40,
            'high'        => 13.85,
            'low'         => 12.80,
            'change'      => -0.04,
            'change_pct'  => -0.30,
            'instrument_key' => 'NSE_INDEX|India VIX',
        ];

        if (!$token) {
            return $defaultVix;
        }

        try {
            $res = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(3)->get('https://api.upstox.com/v2/market-quote/quotes?instrument_key=NSE_INDEX|India%20VIX');

            if ($res->successful()) {
                $json = $res->json();
                $vixData = $json['data']['NSE_INDEX:India VIX'] ?? null;
                if ($vixData) {
                    $lastPrice = (float) ($vixData['last_price'] ?? 13.37);
                    $ohlc = $vixData['ohlc'] ?? [];
                    $open = (float) ($ohlc['open'] ?? $lastPrice);
                    $change = (float) ($vixData['net_change'] ?? 0);
                    $prevClose = (float) ($ohlc['close'] ?? ($lastPrice - $change));
                    $changePct = $prevClose > 0 ? round(($change / $prevClose) * 100, 2) : 0;

                    return [
                        'value'          => $lastPrice,
                        'open'           => $open,
                        'high'           => (float) ($ohlc['high'] ?? $lastPrice),
                        'low'            => (float) ($ohlc['low'] ?? $lastPrice),
                        'change'         => $change,
                        'change_pct'     => $changePct,
                        'instrument_key' => 'NSE_INDEX|India VIX',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fallback gracefully
        }

        return $defaultVix;
    }

    private function buildPayload(Request $request): array
    {
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        if (!in_array($symbol, ['NIFTY', 'BANKNIFTY'])) {
            $symbol = 'NIFTY';
        }

        $strikeStep = $symbol === 'BANKNIFTY' ? 100 : 50;
        $lotSize = $symbol === 'BANKNIFTY' ? 15 : 25; // Standard NSE lot sizes

        // 1. Working date
        $workingDay = DB::table('nse_working_days')
            ->where('current', 1)
            ->first();

        if (!$workingDay) {
            $workingDay = DB::table('nse_working_days')
                ->where('working_date', '<=', Carbon::today()->format('Y-m-d'))
                ->orderBy('working_date', 'desc')
                ->first();
        }

        $workingDate = $workingDay ? $workingDay->working_date : Carbon::today()->format('Y-m-d');

        // 2. Expiries
        $expiries = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'OPT')
            ->orderBy('expiry_date')
            ->get();

        $currentExpiryRow = $expiries->firstWhere('is_current', 1) ?? $expiries->first();
        $selectedExpiry = $request->input('expiry', $currentExpiryRow ? $currentExpiryRow->expiry_date : null);

        // 3. Underlying spot & open from daily_trend
        $dailyTrend = DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $selectedExpiry)
            ->where(function ($q) use ($workingDate) {
                $q->where('quote_date', $workingDate)
                  ->orWhere('trading_date', $workingDate);
            })
            ->first();

        if (!$dailyTrend) {
            $dailyTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->orderByDesc('id')
                ->first();
        }

        $indexOpen = $dailyTrend && !empty($dailyTrend->current_day_index_open) ? (float) $dailyTrend->current_day_index_open : 0;
        $indexClose = $dailyTrend && !empty($dailyTrend->index_close) ? (float) $dailyTrend->index_close : 0;
        $indexKey = $symbol === 'BANKNIFTY' ? 'NSE_INDEX|Nifty Bank' : 'NSE_INDEX|Nifty 50';

        $tableName = function_exists('getTableName') ? getTableName('ohlc_quotes') : 'ohlc_quotes';
        $indexSpot = $indexClose > 0 ? $indexClose : $indexOpen;
        if (!$indexSpot || $indexSpot == 0) {
            $latestIndexQuote = DB::table($tableName)
                ->where('instrument_key', $indexKey)
                ->orderByDesc('id')
                ->first();
            if ($latestIndexQuote) {
                $indexSpot = (float) $latestIndexQuote->close;
            }
        }
        if (!$indexSpot) {
            $indexSpot = $symbol === 'BANKNIFTY' ? 51000 : 23300;
        }

        // ATM Strike
        $defaultAtm = (int) (round($indexSpot / $strikeStep) * $strikeStep);
        $atmStrike = $request->filled('atm') ? (int) $request->input('atm') : $defaultAtm;

        // Strike Range
        $range = max(3, min((int) $request->input('range', 8), 25));

        // Generate strikes list around ATM
        $strikesList = [];
        for ($i = -$range; $i <= $range; $i++) {
            $strikeVal = $atmStrike + ($i * $strikeStep);
            if ($strikeVal > 0) {
                $strikesList[] = $strikeVal;
            }
        }

        // 4. Fetch instruments and option chain data
        $instruments = DB::table('instruments')
            ->where('name', $symbol)
            ->where('expiry', $selectedExpiry)
            ->whereIn('strike_price', $strikesList)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->get();

        if ($instruments->isNotEmpty() && !empty($instruments->first()->lot_size)) {
            $lotSize = (int) $instruments->first()->lot_size;
        }

        $instKeys = $instruments->pluck('instrument_key')->toArray();

        // Latest quotes from ohlc_quotes as fallback
        $fallbackQuotes = [];
        if (!empty($instKeys)) {
            $quotes = DB::table($tableName)
                ->whereIn('instrument_key', $instKeys)
                ->orderByDesc('id')
                ->get()
                ->unique('instrument_key');

            foreach ($quotes as $q) {
                $fallbackQuotes[$q->instrument_key] = [
                    'close'  => (float) $q->close,
                    'volume' => (int) ($q->volume ?? 0),
                    'oi'     => (int) ($q->oi ?? 0),
                ];
            }
        }

        // Option chains table for Greeks, OI, Diff OI, Volume, Buildup
        $chainTable = 'option_chains';
        $chainData = DB::table($chainTable)
            ->where('trading_symbol', $symbol)
            ->where('expiry', $selectedExpiry)
            ->whereIn('strike_price', $strikesList)
            ->get()
            ->groupBy(fn($item) => ((int) $item->strike_price) . '_' . strtoupper($item->option_type));

        $instrumentsMap = [];
        foreach ($instruments as $inst) {
            $strike = (int) $inst->strike_price;
            $type = strtoupper($inst->instrument_type);
            $key = $strike . '_' . $type;

            $chainItem = $chainData->get($key)?->first();
            $fb = $fallbackQuotes[$inst->instrument_key] ?? null;

            $ltp = $chainItem ? (float) $chainItem->ltp : ($fb['close'] ?? 0);
            $oi = $chainItem ? (int) $chainItem->oi : ($fb['oi'] ?? 0);
            $prevOi = $chainItem ? (int) ($chainItem->prev_oi ?? 0) : 0;
            $diffOi = $chainItem && $chainItem->diff_oi !== null ? (int) $chainItem->diff_oi : ($prevOi > 0 ? $oi - $prevOi : 0);
            $oiChangePct = $prevOi > 0 ? round(($diffOi / $prevOi) * 100, 2) : 0;
            $volume = $chainItem ? (int) $chainItem->volume : ($fb['volume'] ?? 0);
            $delta = $chainItem && $chainItem->delta !== null ? (float) $chainItem->delta : ($type === 'CE' ? 0.50 : -0.50);
            $theta = $chainItem ? (float) ($chainItem->theta ?? 0) : 0;
            $vega = $chainItem ? (float) ($chainItem->vega ?? 0) : 0;
            $gamma = $chainItem ? (float) ($chainItem->gamma ?? 0) : 0;
            $iv = $chainItem ? (float) ($chainItem->iv ?? 0) : 0;
            $buildup = $chainItem ? ($chainItem->build_up ?? null) : null;

            if (!$buildup) {
                if ($diffOi > 0 && ($chainItem->diff_ltp ?? 0) > 0) $buildup = 'Long Buildup';
                elseif ($diffOi > 0 && ($chainItem->diff_ltp ?? 0) < 0) $buildup = 'Short Buildup';
                elseif ($diffOi < 0 && ($chainItem->diff_ltp ?? 0) > 0) $buildup = 'Short Covering';
                elseif ($diffOi < 0 && ($chainItem->diff_ltp ?? 0) < 0) $buildup = 'Long Unwinding';
                else $buildup = 'Neutral';
            }

            $instrumentsMap[$strike][$type] = [
                'instrument_key' => $inst->instrument_key,
                'trading_symbol' => $inst->trading_symbol,
                'strike'         => $strike,
                'type'           => $type,
                'ltp'            => $ltp,
                'oi'             => $oi,
                'diff_oi'        => $diffOi,
                'oi_change_pct'  => $oiChangePct,
                'volume'         => $volume,
                'delta'          => $delta,
                'theta'          => $theta,
                'vega'           => $vega,
                'gamma'          => $gamma,
                'iv'             => $iv,
                'buildup'        => $buildup,
            ];
        }

        // 5. India VIX data & Calculations
        $vix = $this->fetchIndiaVix();
        $vixValue = $vix['value'];

        // VIX 1-day move: Spot * (VIX / sqrt(365))
        $vix1DayMove = round($indexSpot * (($vixValue / 100) / sqrt(365)), 2);
        // VIX Weekly move: Spot * (VIX / sqrt(52))
        $vixWeeklyMove = round($indexSpot * (($vixValue / 100) / sqrt(52)), 2);

        // 6. Build Strike Rows & Scanner Pairs
        $strikesData = [];
        $scannerPairs = [];
        $allInstrumentKeys = [$indexKey, $vix['instrument_key']];

        foreach ($strikesList as $strike) {
            $ce = $instrumentsMap[$strike]['CE'] ?? null;
            $pe = $instrumentsMap[$strike]['PE'] ?? null;

            if ($ce && !empty($ce['instrument_key'])) {
                $allInstrumentKeys[] = $ce['instrument_key'];
            }
            if ($pe && !empty($pe['instrument_key'])) {
                $allInstrumentKeys[] = $pe['instrument_key'];
            }

            $strikesData[] = [
                'strike' => $strike,
                'is_atm' => ($strike === $atmStrike),
                'offset' => ($strike - $atmStrike) / $strikeStep,
                'ce'     => $ce,
                'pe'     => $pe,
            ];
        }

        // Create paired strategies (Straddles & Strangles)
        // A. ATM Straddle
        $atmCe = $instrumentsMap[$atmStrike]['CE'] ?? null;
        $atmPe = $instrumentsMap[$atmStrike]['PE'] ?? null;
        if ($atmCe && $atmPe) {
            $comb = round($atmCe['ltp'] + $atmPe['ltp'], 2);
            $lowerBe = round($atmStrike - $comb, 2);
            $upperBe = round($atmStrike + $comb, 2);
            $netDelta = round($atmCe['delta'] + $atmPe['delta'], 3);

            $scannerPairs[] = [
                'id'              => 'atm_straddle',
                'name'            => 'ATM Straddle',
                'tag'             => 'High Theta Decay',
                'preset'          => 'ATM',
                'ce_strike'       => $atmStrike,
                'pe_strike'       => $atmStrike,
                'ce_price'        => $atmCe['ltp'],
                'pe_price'        => $atmPe['ltp'],
                'ce_key'          => $atmCe['instrument_key'],
                'pe_key'          => $atmPe['instrument_key'],
                'combined_credit' => $comb,
                'lower_breakeven' => $lowerBe,
                'upper_breakeven' => $upperBe,
                'safety_pts'      => $comb,
                'safety_pct'      => round(($comb / $indexSpot) * 100, 2),
                'net_delta'       => $netDelta,
                'ce_oi_change'    => $atmCe['oi_change_pct'],
                'pe_oi_change'    => $atmPe['oi_change_pct'],
                'ce_buildup'      => $atmCe['buildup'],
                'pe_buildup'      => $atmPe['buildup'],
            ];
        }

        // B. Strangles at various offsets: ±2, ±3, ±4, ±5
        $offsets = [2, 3, 4, 5];
        foreach ($offsets as $off) {
            $peStrike = $atmStrike - ($off * $strikeStep);
            $ceStrike = $atmStrike + ($off * $strikeStep);

            $pe = $instrumentsMap[$peStrike]['PE'] ?? null;
            $ce = $instrumentsMap[$ceStrike]['CE'] ?? null;

            if ($pe && $ce && $pe['ltp'] > 0 && $ce['ltp'] > 0) {
                $comb = round($ce['ltp'] + $pe['ltp'], 2);
                $lowerBe = round($peStrike - $comb, 2);
                $upperBe = round($ceStrike + $comb, 2);
                $safetyPts = round(min(abs($indexSpot - $lowerBe), abs($upperBe - $indexSpot)), 2);
                $netDelta = round($ce['delta'] + $pe['delta'], 3);

                $label = "±{$off} OTM Strangle";
                $tag = $off >= 4 ? 'Conservative Safe (15 Delta)' : 'Balanced (25 Delta)';

                $scannerPairs[] = [
                    'id'              => "strangle_{$off}",
                    'name'            => $label,
                    'tag'             => $tag,
                    'preset'          => "OTM_{$off}",
                    'ce_strike'       => $ceStrike,
                    'pe_strike'       => $peStrike,
                    'ce_price'        => $ce['ltp'],
                    'pe_price'        => $pe['ltp'],
                    'ce_key'          => $ce['instrument_key'],
                    'pe_key'          => $pe['instrument_key'],
                    'combined_credit' => $comb,
                    'lower_breakeven' => $lowerBe,
                    'upper_breakeven' => $upperBe,
                    'safety_pts'      => $safetyPts,
                    'safety_pct'      => round(($safetyPts / $indexSpot) * 100, 2),
                    'net_delta'       => $netDelta,
                    'ce_oi_change'    => $ce['oi_change_pct'],
                    'pe_oi_change'    => $pe['oi_change_pct'],
                    'ce_buildup'      => $ce['buildup'],
                    'pe_buildup'      => $pe['buildup'],
                ];
            }
        }

        // C. Equal Premium Matcher (Finds pair with closest LTP)
        $bestPair = null;
        $minDiff = 999999;
        foreach ($strikesList as $sPe) {
            if ($sPe >= $atmStrike) continue;
            $pe = $instrumentsMap[$sPe]['PE'] ?? null;
            if (!$pe || $pe['ltp'] <= 5) continue;

            foreach ($strikesList as $sCe) {
                if ($sCe <= $atmStrike) continue;
                $ce = $instrumentsMap[$sCe]['CE'] ?? null;
                if (!$ce || $ce['ltp'] <= 5) continue;

                $diff = abs($ce['ltp'] - $pe['ltp']);
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $bestPair = [$sCe, $sPe, $ce, $pe, $diff];
                }
            }
        }

        if ($bestPair) {
            [$ceS, $peS, $ceO, $peO, $diff] = $bestPair;
            $comb = round($ceO['ltp'] + $peO['ltp'], 2);
            $lowerBe = round($peS - $comb, 2);
            $upperBe = round($ceS + $comb, 2);
            $safetyPts = round(min(abs($indexSpot - $lowerBe), abs($upperBe - $indexSpot)), 2);

            $scannerPairs[] = [
                'id'              => 'equal_premium',
                'name'            => "Equal Premium ({$peS} PE / {$ceS} CE)",
                'tag'             => "Diff ₹{$diff} | Balanced Neutral",
                'preset'          => 'EQUAL_PREMIUM',
                'ce_strike'       => $ceS,
                'pe_strike'       => $peS,
                'ce_price'        => $ceO['ltp'],
                'pe_price'        => $peO['ltp'],
                'ce_key'          => $ceO['instrument_key'],
                'pe_key'          => $peO['instrument_key'],
                'combined_credit' => $comb,
                'lower_breakeven' => $lowerBe,
                'upper_breakeven' => $upperBe,
                'safety_pts'      => $safetyPts,
                'safety_pct'      => round(($safetyPts / $indexSpot) * 100, 2),
                'net_delta'       => round($ceO['delta'] + $peO['delta'], 3),
                'ce_oi_change'    => $ceO['oi_change_pct'],
                'pe_oi_change'    => $peO['oi_change_pct'],
                'ce_buildup'      => $ceO['buildup'],
                'pe_buildup'      => $peO['buildup'],
            ];
        }

        return [
            'symbol'             => $symbol,
            'lotSize'            => $lotSize,
            'workingDate'        => $workingDate,
            'expiries'           => $expiries,
            'selectedExpiry'     => $selectedExpiry,
            'indexSpot'          => $indexSpot,
            'indexOpen'          => $indexOpen,
            'indexKey'           => $indexKey,
            'atmStrike'          => $atmStrike,
            'strikeStep'         => $strikeStep,
            'range'              => $range,
            'vix'                => $vix,
            'vix1DayMove'        => $vix1DayMove,
            'vixWeeklyMove'      => $vixWeeklyMove,
            'strikesData'        => $strikesData,
            'scannerPairs'       => $scannerPairs,
            'allInstrumentKeys'  => array_values(array_unique($allInstrumentKeys)),
            'updatedAt'          => now()->format('d M Y, h:i:s A'),
        ];
    }
}
