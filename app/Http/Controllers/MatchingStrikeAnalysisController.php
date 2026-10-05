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
        $maxGreekDelta = $request->input('max_greek_delta'); // optional greek delta difference limit
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

        // 4. Resolve Expiries (Current Week & Next Week)
        $expiries = [];
        if ($actualTs && Schema::hasTable($table)) {
            $expiries = DB::table($table)
                ->where('trading_symbol', $symbol)
                ->where('captured_at', $actualTs)
                ->distinct()
                ->pluck('expiry')
                ->sort()
                ->values()
                ->take(2)
                ->toArray();
        }

        // Fallback to nse_expiries table if less than 2 expiries found
        if (count($expiries) < 2 && Schema::hasTable('nse_expiries')) {
            $dbExpiries = DB::table('nse_expiries')
                ->where('trading_symbol', $symbol)
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $selectedDate)
                ->orderBy('expiry_date')
                ->take(2)
                ->pluck('expiry_date')
                ->toArray();

            foreach ($dbExpiries as $de) {
                if (!in_array($de, $expiries, true)) {
                    $expiries[] = $de;
                }
            }
        }

        // 5. Build Matching Pairs for Each Expiry Column
        $columnsData = [];

        foreach ($expiries as $idx => $expiry) {
            $rows = collect();
            if ($actualTs && Schema::hasTable($table)) {
                $selectCols = ['strike_price', 'option_type', 'ltp', 'delta', 'underlying_spot_price', 'volume', 'oi'];
                if (Schema::hasColumn($table, 'instrument_key')) {
                    $selectCols[] = 'instrument_key';
                }
                $rows = DB::table($table)
                    ->where('trading_symbol', $symbol)
                    ->where('captured_at', $actualTs)
                    ->where('expiry', $expiry)
                    ->get($selectCols);
            }

            $spotPrice = (float)($rows->first()->underlying_spot_price ?? 22550.0);
            $calculatedAtm = round($spotPrice / $step) * $step;
            $atmStrike = ($customAtm !== null && $customAtm > 0) ? $customAtm : $calculatedAtm;

            // CE rows in price range
            $ceRows = $rows->where('option_type', 'CE')
                ->where('ltp', '>=', $minPrice)
                ->where('ltp', '<=', $maxPrice)
                ->sortBy('strike_price')
                ->values();

            // PE rows in price range
            $peRows = $rows->where('option_type', 'PE')
                ->where('ltp', '>=', $minPrice)
                ->where('ltp', '<=', $maxPrice)
                ->sortBy('strike_price')
                ->values();

            // Matching pairs where |CE price - PE price| <= maxPriceDiff
            $matchedPairs = [];
            foreach ($ceRows as $ce) {
                foreach ($peRows as $pe) {
                    $priceDiff = abs((float)$ce->ltp - (float)$pe->ltp);
                    if ($priceDiff <= $maxPriceDiff) {
                        $greekDiff = abs((float)$ce->delta - abs((float)$pe->delta));

                        // Optional greek delta filter if specified
                        if ($maxGreekDelta !== null && $maxGreekDelta !== '' && is_numeric($maxGreekDelta)) {
                            if ($greekDiff > (float)$maxGreekDelta) {
                                continue;
                            }
                        }

                        $ceDist = (float)$ce->strike_price - $atmStrike;
                        $peDist = $atmStrike - (float)$pe->strike_price;

                        $matchedPairs[] = [
                            'ce_delta' => (float)$ce->delta,
                            'ce_price' => (float)$ce->ltp,
                            'ce_strike' => (float)$ce->strike_price,
                            'ce_dist' => (int)$ceDist,
                            'atm' => (int)$atmStrike,
                            'pe_dist' => (int)$peDist,
                            'pe_strike' => (float)$pe->strike_price,
                            'pe_price' => (float)$pe->ltp,
                            'pe_delta' => (float)$pe->delta,
                            'price_diff' => round($priceDiff, 2),
                            'greek_diff' => round($greekDiff, 4),
                            'combined_price' => round((float)$ce->ltp + (float)$pe->ltp, 2),
                            'net_delta' => round((float)$ce->delta + (float)$pe->delta, 4),
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

            $columnsData[] = [
                'expiry' => $expiry,
                'is_current' => ($idx === 0),
                'is_next' => ($idx === 1),
                'label' => ($idx === 0) ? 'Current Week Expiry' : 'Next Week Expiry',
                'spot_price' => $spotPrice,
                'atm_strike' => $atmStrike,
                'ce_count' => $ceRows->count(),
                'pe_count' => $peRows->count(),
                'ce_rows' => $ceRows,
                'pe_rows' => $peRows,
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
