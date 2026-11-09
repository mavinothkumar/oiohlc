<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class LiveStraddleRadarController extends Controller
{
    /**
     * Display the Live Straddle Decay & Expansion Radar.
     */
    public function index(Request $request)
    {
        $payload = $this->buildRadarPayload($request);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'data' => $payload,
            ]);
        }

        return view('live-straddle-radar.index', $payload);
    }

    /**
     * AJAX data endpoint for dynamic refresh.
     */
    public function getData(Request $request)
    {
        $payload = $this->buildRadarPayload($request);
        return response()->json([
            'ok' => true,
            'data' => $payload,
        ]);
    }

    /**
     * Provide authorized WebSocket redirect URI for Upstox V3 Market Data Feed.
     */
    public function getWsUrl()
    {
        $token = config('services.upstox.analytics_token') ?: config('services.upstox.access_token');
        if (!$token) {
            return response()->json([
                'ok' => false,
                'error' => 'Upstox access token not configured in services.upstox.',
            ], 400);
        }

        try {
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(10)->get('https://api.upstox.com/v3/feed/market-data-feed/authorize');

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'ok' => false,
                'error' => 'Failed to fetch WS URL from Upstox',
                'details' => $response->body(),
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Exception fetching Upstox WS URL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build the complete radar payload.
     */
    private function buildRadarPayload(Request $request): array
    {
        $symbol = strtoupper($request->query('symbol', 'NIFTY'));
        $symbols = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'SENSEX', 'BANKEX'];

        $step = ($symbol === 'BANKNIFTY' || $symbol === 'SENSEX' || $symbol === 'BANKEX') ? 100 : 50;

        // 1. Fetch available expiries for this symbol
        $expiryRows = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->select(
                'expiry_date',
                DB::raw('COUNT(DISTINCT quote_date) as day_count'),
                DB::raw('MIN(quote_date) as min_date'),
                DB::raw('MAX(quote_date) as max_date')
            )
            ->groupBy('expiry_date')
            ->orderByDesc('expiry_date')
            ->get();

        $expiries = $expiryRows->map(function ($row) {
            return [
                'expiry_date' => $row->expiry_date,
                'formatted' => Carbon::parse($row->expiry_date)->format('d M Y'),
                'day_count' => (int) $row->day_count,
            ];
        })->values();

        if ($expiries->isEmpty()) {
            return $this->emptyResponse('No expiry data found for symbol ' . $symbol, $symbol, $symbols);
        }

        // 2. Determine selected expiry
        $selectedExpiry = $request->query('expiry', $expiries->first()['expiry_date']);
        $currentExpiryMeta = $expiries->firstWhere('expiry_date', $selectedExpiry) ?? $expiries->first();
        $selectedExpiry = $currentExpiryMeta['expiry_date'];

        // 3. Fetch all trading dates for this expiry in ascending order
        $tradingDates = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $selectedExpiry)
            ->select('quote_date')
            ->distinct()
            ->orderBy('quote_date', 'asc')
            ->pluck('quote_date')
            ->toArray();

        if (empty($tradingDates)) {
            return $this->emptyResponse('No trading records for this expiry.', $symbol, $symbols, $expiries);
        }

        // 4. Latest date (Today / current session) and Yesterday (Previous Close baseline)
        $latestDate = end($tradingDates);
        $prevDate = count($tradingDates) > 1 ? $tradingDates[count($tradingDates) - 2] : null;

        // 5. Fetch Spot Index Close
        $spotRow = DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->where('trading_date', $latestDate)
            ->first();

        $spotPrice = $spotRow && $spotRow->index_close ? (float) $spotRow->index_close : null;

        // Fallback spot if not found
        if (!$spotPrice) {
            $spotPrice = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->orderByDesc('trading_date')
                ->value('index_close');
            $spotPrice = $spotPrice ? (float) $spotPrice : 25000.0;
        }

        $atmStrike = (int) (round($spotPrice / $step) * $step);

        // 6. Filter Strikes Corridor (Default ±10 strikes = 21 strikes)
        $strikeRangeFilter = $request->query('strike_range', '10');
        $rangeCount = is_numeric($strikeRangeFilter) ? (int)$strikeRangeFilter : 10;
        $minStrike = $atmStrike - ($rangeCount * $step);
        $maxStrike = $atmStrike + ($rangeCount * $step);

        // 7. Fetch all quotes for this corridor across all dates
        $quotes = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $selectedExpiry)
            ->whereBetween('strike', [$minStrike, $maxStrike])
            ->get();

        $grouped = [];
        $instrumentKeys = [];
        $allSubscribedKeys = [];

        foreach ($quotes as $q) {
            $k = (int) $q->strike;
            $type = strtoupper($q->option_type);
            $grouped[$k][$q->quote_date][$type] = $q;

            if ($q->instrument_key) {
                $instrumentKeys[$k][$type] = $q->instrument_key;
                $allSubscribedKeys[] = $q->instrument_key;
            }
        }

        // Add index instrument key
        $indexKey = ($symbol === 'NIFTY') ? 'NSE_INDEX|Nifty 50' : 'NSE_INDEX|' . $symbol;
        $allSubscribedKeys[] = $indexKey;
        $allSubscribedKeys = array_values(array_unique($allSubscribedKeys));

        // Format historical date headers (all days up to yesterday)
        // If only 1 day exists, historical column is that day
        $historicalDates = $tradingDates;
        // If latest day is being streamed, yesterday is the last historical column
        if ($prevDate !== null) {
            $historicalDates = array_slice($tradingDates, 0, count($tradingDates) - 1);
        }

        $dayHeaders = [];
        foreach ($historicalDates as $idx => $date) {
            $isYesterday = ($date === $prevDate);
            $dayHeaders[] = [
                'day_number' => $idx + 1,
                'quote_date' => $date,
                'formatted' => Carbon::parse($date)->format('d M'),
                'is_yesterday' => $isYesterday,
            ];
        }

        // 8. Build Strike-by-Strike Corridor Records
        $strikesData = [];
        $totalInitialOverpriced = 0;
        $maxObservedSpike = 0;

        for ($k = $minStrike; $k <= $maxStrike; $k += $step) {
            $isAtm = ($k === $atmStrike);
            $diffFromAtm = $k - $atmStrike;
            $moneyness = ($k < $atmStrike) ? 'CE ITM / PE OTM' : (($k === $atmStrike) ? 'ATM Anchor' : 'CE OTM / PE ITM');

            $ceKey = $instrumentKeys[$k]['CE'] ?? null;
            $peKey = $instrumentKeys[$k]['PE'] ?? null;

            // Historical straddle progression for this strike
            $historicalDays = [];
            foreach ($historicalDates as $hDate) {
                $hCe = $grouped[$k][$hDate]['CE'] ?? null;
                $hPe = $grouped[$k][$hDate]['PE'] ?? null;
                $hCeClose = ($hCe && $hCe->close > 0) ? (float)$hCe->close : null;
                $hPeClose = ($hPe && $hPe->close > 0) ? (float)$hPe->close : null;
                $hStraddle = ($hCeClose !== null && $hPeClose !== null) ? round($hCeClose + $hPeClose, 2) : null;

                $historicalDays[] = [
                    'quote_date' => $hDate,
                    'straddle' => $hStraddle,
                    'ce_close' => $hCeClose,
                    'pe_close' => $hPeClose,
                ];
            }

            // Yesterday Baseline
            $prevCe = $prevDate ? ($grouped[$k][$prevDate]['CE'] ?? null) : null;
            $prevPe = $prevDate ? ($grouped[$k][$prevDate]['PE'] ?? null) : null;
            $prevCeClose = ($prevCe && $prevCe->close > 0) ? (float)$prevCe->close : null;
            $prevPeClose = ($prevPe && $prevPe->close > 0) ? (float)$prevPe->close : null;
            $yesterdayStraddle = ($prevCeClose !== null && $prevPeClose !== null) ? round($prevCeClose + $prevPeClose, 2) : null;

            // Current Session (Seed for Live stream before ticks arrive)
            $currCe = $grouped[$k][$latestDate]['CE'] ?? null;
            $currPe = $grouped[$k][$latestDate]['PE'] ?? null;
            $currCeLtp = $currCe ? (float)$currCe->close : ($prevCeClose ?? 0);
            $currPeLtp = $currPe ? (float)$currPe->close : ($prevPeClose ?? 0);
            $liveStraddle = round($currCeLtp + $currPeLtp, 2);

            // Diff vs Yesterday Close
            $diffPts = ($yesterdayStraddle !== null) ? round($liveStraddle - $yesterdayStraddle, 2) : 0;
            $diffPct = ($yesterdayStraddle !== null && $yesterdayStraddle > 0) ? round(($diffPts / $yesterdayStraddle) * 100, 2) : 0;
            $isOverpriced = ($diffPts > 0);

            if ($isOverpriced) {
                $totalInitialOverpriced++;
                if ($diffPts > $maxObservedSpike) {
                    $maxObservedSpike = $diffPts;
                }
            }

            $strikesData[] = [
                'strike' => $k,
                'is_atm' => $isAtm,
                'diff_atm' => $diffFromAtm,
                'moneyness' => $moneyness,
                'ce_key' => $ceKey,
                'pe_key' => $peKey,
                'historical_days' => $historicalDays,
                'yesterday_close' => [
                    'straddle' => $yesterdayStraddle,
                    'ce' => $prevCeClose,
                    'pe' => $prevPeClose,
                ],
                'live' => [
                    'ce_ltp' => $currCeLtp,
                    'pe_ltp' => $currPeLtp,
                    'straddle' => $liveStraddle,
                    'diff_pts' => $diffPts,
                    'diff_pct' => $diffPct,
                    'is_overpriced' => $isOverpriced,
                ],
            ];
        }

        return [
            'symbol' => $symbol,
            'symbols' => $symbols,
            'expiries' => $expiries,
            'selected_expiry' => $selectedExpiry,
            'selected_expiry_formatted' => Carbon::parse($selectedExpiry)->format('d M Y'),
            'trading_dates' => $tradingDates,
            'latest_date' => $latestDate,
            'latest_date_formatted' => Carbon::parse($latestDate)->format('d M Y'),
            'prev_date' => $prevDate,
            'prev_date_formatted' => $prevDate ? Carbon::parse($prevDate)->format('d M Y') : 'N/A',
            'day_headers' => $dayHeaders,
            'spot' => [
                'symbol' => $symbol,
                'price' => $spotPrice,
                'atm_strike' => $atmStrike,
                'index_key' => $indexKey,
            ],
            'corridor' => [
                'range' => $strikeRangeFilter,
                'min_strike' => $minStrike,
                'max_strike' => $maxStrike,
                'step' => $step,
                'strikes_count' => count($strikesData),
            ],
            'strikes' => $strikesData,
            'subscribed_keys' => $allSubscribedKeys,
            'stats' => [
                'overpriced_count' => $totalInitialOverpriced,
                'max_spike' => $maxObservedSpike,
            ],
        ];
    }

    /**
     * Fallback empty response structure.
     */
    private function emptyResponse(string $message, string $symbol, array $symbols, $expiries = []): array
    {
        return [
            'symbol' => $symbol,
            'symbols' => $symbols,
            'expiries' => $expiries,
            'selected_expiry' => null,
            'selected_expiry_formatted' => 'N/A',
            'trading_dates' => [],
            'latest_date' => null,
            'latest_date_formatted' => 'N/A',
            'prev_date' => null,
            'prev_date_formatted' => 'N/A',
            'day_headers' => [],
            'spot' => ['symbol' => $symbol, 'price' => 25000, 'atm_strike' => 25000, 'index_key' => 'NSE_INDEX|Nifty 50'],
            'corridor' => ['range' => '10', 'min_strike' => 24500, 'max_strike' => 25500, 'step' => 50, 'strikes_count' => 0],
            'strikes' => [],
            'subscribed_keys' => [],
            'stats' => ['overpriced_count' => 0, 'max_spike' => 0],
            'message' => $message,
        ];
    }
}
