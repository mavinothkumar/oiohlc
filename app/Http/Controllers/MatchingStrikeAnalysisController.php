<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MatchingStrikeAnalysisController extends Controller
{
    /**
     * Display the Matching Strike Analysis (Current & Next Week Expiry).
     */
    public function index(Request $request): View
    {
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $step = ($symbol === 'BANKNIFTY') ? 100.0 : 50.0;

        $table = getTableName('option_chains');
        if (!Schema::hasTable($table) && Schema::hasTable('option_chains')) {
            $table = 'option_chains';
        }

        // 1. Available Dates
        $availableDates = [];
        if (Schema::hasTable('nse_working_days')) {
            $availableDates = DB::table('nse_working_days')
                ->where('working_date', '<=', today()->toDateString())
                ->orderByDesc('working_date')
                ->take(30)
                ->pluck('working_date')
                ->toArray();
        }

        if (empty($availableDates) && Schema::hasTable($table)) {
            $availableDates = DB::table($table)
                ->selectRaw('DATE(captured_at) as cdate')
                ->distinct()
                ->orderByDesc('cdate')
                ->take(30)
                ->pluck('cdate')
                ->toArray();
        }

        if (empty($availableDates)) {
            $availableDates = [today()->toDateString()];
        }

        $selectedDate = $request->input('date', $availableDates[0] ?? today()->toDateString());

        // 2. Filter Parameters
        $minPrice = (float)$request->input('min_price', 30.0);
        $maxPrice = (float)$request->input('max_price', 60.0);
        $maxPriceDiff = (float)$request->input('max_delta', 3.0); // max price difference / delta
        $maxGreekDelta = $request->input('max_greek_delta'); // max individual delta for CE and PE legs (e.g. 0.2 => |delta| <= 0.2)
        $maxDeltaDiff = $request->input('max_delta_diff'); // optional max difference between CE delta and PE delta (|CE delta| - |PE delta|)
        $customAtm = $request->input('custom_atm');
        $customAtm = ($customAtm !== null && $customAtm !== '' && is_numeric($customAtm)) ? (float)$customAtm : null;

        // 3. Resolve Snapshot Timestamp for Selected Date
        $requestedTime = $request->input('time');
        $actualTs = null;

        if (Schema::hasTable($table)) {
            $tsQuery = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->whereDate('captured_at', $selectedDate);

            if (!empty($requestedTime)) {
                $targetTs = Carbon::parse("{$selectedDate} {$requestedTime}")->toDateTimeString();
                $actualTs = (clone $tsQuery)
                    ->where('captured_at', '<=', $targetTs)
                    ->max('captured_at');
            }

            if (!$actualTs) {
                $actualTs = (clone $tsQuery)->max('captured_at');
            }
        }

        // 4. Resolve Expiries (Up to 5 Expiries)
        $expiries = [];
        if ($actualTs && Schema::hasTable($table)) {
            $expiries = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('captured_at', $actualTs)
                ->distinct()
                ->pluck('expiry')
                ->sort()
                ->values()
                ->take(5)
                ->toArray();
        }

        // Fallback to nse_expiries table if less than 5 expiries found
        if (count($expiries) < 5 && Schema::hasTable('nse_expiries')) {
            $dbExpiries = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $selectedDate)
                ->orderBy('expiry_date')
                ->take(5)
                ->pluck('expiry_date')
                ->toArray();

            foreach ($dbExpiries as $de) {
                if (!in_array($de, $expiries, true)) {
                    $expiries[] = $de;
                }
            }
            $expiries = array_slice($expiries, 0, 5);
        }

        // 5. Build Matching Pairs for Each Expiry Column
        $columnsData = [];

        foreach ($expiries as $idx => $expiry) {
            $rows = collect();
            if (Schema::hasTable($table)) {
                $selectCols = ['strike_price', 'option_type', 'ltp', 'delta', 'underlying_spot_price', 'volume', 'oi'];
                if (Schema::hasColumn($table, 'instrument_key')) {
                    $selectCols[] = 'instrument_key';
                }
                if (Schema::hasColumn($table, 'iv')) {
                    $selectCols[] = 'iv';
                }

                if ($actualTs) {
                    $rows = DB::table($table)
                        ->where('trading_symbol', $symbol)
                        ->where('captured_at', $actualTs)
                        ->where('expiry', $expiry)
                        ->get($selectCols);
                }

                // If no records found at exact $actualTs, look for closest snapshot for this expiry on selected date
                if ($rows->isEmpty()) {
                    $expTsQuery = DB::table($table)
                        ->where('trading_symbol', $symbol)
                        ->where('expiry', $expiry)
                        ->whereDate('captured_at', $selectedDate);

                    if (!empty($requestedTime)) {
                        $targetTs = Carbon::parse("{$selectedDate} {$requestedTime}")->toDateTimeString();
                        $expTsQuery->where('captured_at', '<=', $targetTs);
                    }

                    $expActualTs = $expTsQuery->max('captured_at');
                    if ($expActualTs) {
                        $rows = DB::table($table)
                            ->where('trading_symbol', $symbol)
                            ->where('captured_at', $expActualTs)
                            ->where('expiry', $expiry)
                            ->get($selectCols);
                    }
                }
            }

            $spotPrice = (float)($rows->first()->underlying_spot_price ?? 0);
            if ($spotPrice <= 0) {
                // Fallback to spot from other columns or default
                $spotPrice = 22550.0;
                foreach ($columnsData as $cd) {
                    if ($cd['spot_price'] > 0) {
                        $spotPrice = $cd['spot_price'];
                        break;
                    }
                }
            }

            $calculatedAtm = round($spotPrice / $step) * $step;
            $atmStrike = ($customAtm !== null && $customAtm > 0) ? $customAtm : $calculatedAtm;

            // Days to Expiry (DTE) for Greek delta calculation
            $selectedCarbon = Carbon::parse($selectedDate)->setTime(15, 30);
            $expiryCarbon = Carbon::parse($expiry)->endOfDay();
            $dteHours = $selectedCarbon->diffInHours($expiryCarbon, false);
            $dteDays = max(0.05, $dteHours / 24.0);
            $dteCalendarDays = max(0, Carbon::parse($selectedDate)->diffInDays(Carbon::parse($expiry), false));

            // CE rows in price range with calculated delta
            $ceRows = $rows->where('option_type', 'CE')
                ->where('ltp', '>=', $minPrice)
                ->where('ltp', '<=', $maxPrice)
                ->sortBy('strike_price')
                ->values()
                ->map(function ($ce) use ($spotPrice, $dteDays) {
                    $ceDelta = (float)($ce->delta ?? 0);
                    if ($ceDelta == 0.0 && $spotPrice > 0 && (float)$ce->strike_price > 0) {
                        $ceIv = (float)($ce->iv ?? 13.5);
                        if ($ceIv <= 0) $ceIv = 13.5;
                        $greeks = \App\Services\Quant\ProbabilityEngine::calculateGreeks($spotPrice, (float)$ce->strike_price, $dteDays, $ceIv);
                        $ceDelta = (float)$greeks['delta_ce'];
                    }
                    $ce->calculated_delta = round($ceDelta, 4);
                    return $ce;
                });

            // Filter CE rows by max leg delta if specified
            if ($maxGreekDelta !== null && $maxGreekDelta !== '' && is_numeric($maxGreekDelta)) {
                $maxLimit = (float)$maxGreekDelta;
                $ceRows = $ceRows->filter(fn($ce) => abs($ce->calculated_delta) <= $maxLimit)->values();
            }

            // PE rows in price range with calculated delta
            $peRows = $rows->where('option_type', 'PE')
                ->where('ltp', '>=', $minPrice)
                ->where('ltp', '<=', $maxPrice)
                ->sortBy('strike_price')
                ->values()
                ->map(function ($pe) use ($spotPrice, $dteDays) {
                    $peDelta = (float)($pe->delta ?? 0);
                    if ($peDelta == 0.0 && $spotPrice > 0 && (float)$pe->strike_price > 0) {
                        $peIv = (float)($pe->iv ?? 13.5);
                        if ($peIv <= 0) $peIv = 13.5;
                        $greeks = \App\Services\Quant\ProbabilityEngine::calculateGreeks($spotPrice, (float)$pe->strike_price, $dteDays, $peIv);
                        $peDelta = (float)$greeks['delta_pe'];
                    }
                    if ($peDelta > 0) {
                        $peDelta = -$peDelta;
                    }
                    $pe->calculated_delta = round($peDelta, 4);
                    return $pe;
                });

            // Filter PE rows by max leg delta if specified
            if ($maxGreekDelta !== null && $maxGreekDelta !== '' && is_numeric($maxGreekDelta)) {
                $maxLimit = (float)$maxGreekDelta;
                $peRows = $peRows->filter(fn($pe) => abs($pe->calculated_delta) <= $maxLimit)->values();
            }

            // Matching pairs where |CE price - PE price| <= maxPriceDiff
            $matchedPairs = [];
            foreach ($ceRows as $ce) {
                $ceDelta = $ce->calculated_delta;

                foreach ($peRows as $pe) {
                    $priceDiff = abs((float)$ce->ltp - (float)$pe->ltp);
                    if ($priceDiff <= $maxPriceDiff) {
                        $peDelta = $pe->calculated_delta;
                        $greekDiff = abs(abs($ceDelta) - abs($peDelta));

                        // Optional greek delta difference filter (|CE delta| - |PE delta| <= maxDeltaDiff)
                        if ($maxDeltaDiff !== null && $maxDeltaDiff !== '' && is_numeric($maxDeltaDiff)) {
                            if ($greekDiff > (float)$maxDeltaDiff) {
                                continue;
                            }
                        }

                        $ceDist = (float)$ce->strike_price - $atmStrike;
                        $peDist = $atmStrike - (float)$pe->strike_price;

                        $matchedPairs[] = [
                            'ce_delta' => $ceDelta,
                            'ce_price' => (float)$ce->ltp,
                            'ce_strike' => (float)$ce->strike_price,
                            'ce_dist' => (int)$ceDist,
                            'atm' => (int)$atmStrike,
                            'pe_dist' => (int)$peDist,
                            'pe_strike' => (float)$pe->strike_price,
                            'pe_price' => (float)$pe->ltp,
                            'pe_delta' => $peDelta,
                            'price_diff' => round($priceDiff, 2),
                            'greek_diff' => round($greekDiff, 4),
                            'combined_price' => round((float)$ce->ltp + (float)$pe->ltp, 2),
                            'net_delta' => round($ceDelta + $peDelta, 4),
                            'ce_oi' => (int)$ce->oi,
                            'pe_oi' => (int)$pe->oi,
                            'ce_vol' => (int)$ce->volume,
                            'pe_vol' => (int)$pe->volume,
                            'ce_instrument_key' => $ce->instrument_key ?? '',
                            'pe_instrument_key' => $pe->instrument_key ?? '',
                        ];
                    }
                }
            }

            // Sort matched pairs by price difference ascending (closest match first)
            usort($matchedPairs, fn($a, $b) => $a['price_diff'] <=> $b['price_diff']);

            $label = match ($idx) {
                0 => 'Current Week Expiry',
                1 => 'Next Week Expiry',
                2 => 'Expiry 3',
                3 => 'Expiry 4',
                4 => 'Expiry 5',
                default => 'Expiry ' . ($idx + 1),
            };

            $badge = match ($idx) {
                0 => 'CURRENT',
                1 => 'NEXT',
                2 => 'EXPIRY 3',
                3 => 'EXPIRY 4',
                4 => 'EXPIRY 5',
                default => 'EXP ' . ($idx + 1),
            };

            $columnsData[] = [
                'expiry' => $expiry,
                'is_current' => ($idx === 0),
                'is_next' => ($idx === 1),
                'idx' => $idx,
                'label' => $label,
                'badge' => $badge,
                'dte' => $dteCalendarDays,
                'dte_label' => ($dteCalendarDays === 0) ? '0d (Today)' : "{$dteCalendarDays}d DTE",
                'spot_price' => $spotPrice,
                'atm_strike' => $atmStrike,
                'ce_count' => $ceRows->count(),
                'pe_count' => $peRows->count(),
                'matched_pairs' => $matchedPairs,
                'avg_combined' => count($matchedPairs) > 0 ? round(collect($matchedPairs)->avg('combined_price'), 2) : 0,
            ];
        }

        return view('matching-strike-analysis.index', compact(
            'symbol',
            'availableDates',
            'selectedDate',
            'actualTs',
            'minPrice',
            'maxPrice',
            'maxPriceDiff',
            'maxGreekDelta',
            'maxDeltaDiff',
            'customAtm',
            'expiries',
            'columnsData'
        ));
    }

    /**
     * Authorize Upstox WebSocket Feed URL
     */
    public function getWsUrl()
    {
        $token = config('services.upstox.analytics_token');
        if (!$token) {
            return response()->json(['error' => 'Upstox access token not configured in services.upstox.analytics_token'], 400);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
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
}
