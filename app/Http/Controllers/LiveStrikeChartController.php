<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class LiveStrikeChartController extends Controller
{
    public function index(Request $request)
    {
        $payload = $this->buildPayload($request);

        return view('live-strike-chart', $payload);
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

    private function buildPayload(Request $request): array
    {
        $symbol = $request->input('symbol', 'NIFTY');

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
        $currentExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'OPT')
            ->where('is_current', 1)
            ->first();

        $nextExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'OPT')
            ->where('is_next', 1)
            ->first();

        // 3. Daily Trend Data (for Current and Next week mid_point and atm_index_open)
        $currentTrend = null;
        if ($currentExpiry) {
            $currentTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $currentExpiry->expiry_date)
                ->where(function ($q) use ($workingDate) {
                    $q->where('quote_date', $workingDate)
                      ->orWhere('trading_date', $workingDate);
                })
                ->first();

            if (!$currentTrend) {
                $currentTrend = DB::table('daily_trend')
                    ->where('symbol_name', $symbol)
                    ->where('expiry_date', $currentExpiry->expiry_date)
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if (!$currentTrend) {
            $currentTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->orderByDesc('id')
                ->first();
        }

        $nextTrend = null;
        if ($nextExpiry) {
            $nextTrend = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $nextExpiry->expiry_date)
                ->where(function ($q) use ($workingDate) {
                    $q->where('quote_date', $workingDate)
                      ->orWhere('trading_date', $workingDate);
                })
                ->first();

            if (!$nextTrend) {
                $nextTrend = DB::table('daily_trend')
                    ->where('symbol_name', $symbol)
                    ->where('expiry_date', $nextExpiry->expiry_date)
                    ->orderByDesc('id')
                    ->first();
            }
        }

        $currentMidPoint = $currentTrend ? (float) $currentTrend->mid_point : 0;
        $nextMidPoint    = $nextTrend ? (float) $nextTrend->mid_point : 0;

        // Base ATM strike from daily_trend atm_index_open column
        $detectedAtm = 0;
        if ($currentTrend && !empty($currentTrend->atm_index_open)) {
            $detectedAtm = (float) $currentTrend->atm_index_open;
        } elseif ($currentTrend && !empty($currentTrend->strike)) {
            $detectedAtm = (float) $currentTrend->strike;
        } elseif ($currentTrend && !empty($currentTrend->current_day_index_open)) {
            $detectedAtm = (float) $currentTrend->current_day_index_open;
        }

        // Index open price from column current_day_index_open
        $indexOpen = 0;
        if ($currentTrend && !empty($currentTrend->current_day_index_open)) {
            $indexOpen = (float) $currentTrend->current_day_index_open;
        } else {
            $latestOpenRow = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->whereNotNull('current_day_index_open')
                ->where('current_day_index_open', '>', 0)
                ->orderByDesc('id')
                ->first();
            if ($latestOpenRow) {
                $indexOpen = (float) $latestOpenRow->current_day_index_open;
            }
        }

        $indexClose = $currentTrend ? (float) ($currentTrend->index_close ?? 0) : 0;
        $indexKey   = $symbol === 'BANKNIFTY' ? 'NSE_INDEX|Nifty Bank' : 'NSE_INDEX|Nifty 50';

        // Current Index Spot initial fallback
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

        // Round to nearest 50
        $defaultAtmStrike = $detectedAtm > 0 ? (int) (round($detectedAtm / 50) * 50) : 23200;

        // Custom ATM Strike if user specified in request
        $atmStrike = $request->filled('atm') ? (int) $request->input('atm') : $defaultAtmStrike;

        // Strike Range (default +/- 8, customizable to e.g. 2, 3, 4, 5, 8, 10)
        $range = max(1, min((int) $request->input('range', 8), 30));
        $strikeStep = $symbol === 'BANKNIFTY' ? 100 : 50;

        // Generate +/- N strikes around ATM center point
        $strikesList = [];
        for ($i = -$range; $i <= $range; $i++) {
            $strikeVal = $atmStrike + ($i * $strikeStep);
            if ($strikeVal > 0) {
                $strikesList[] = $strikeVal;
            }
        }

        // 4. Fetch instruments for current expiry
        $instruments = collect();
        if ($currentExpiry) {
            $instruments = DB::table('instruments')
                ->where('name', $symbol)
                ->where('expiry', $currentExpiry->expiry)
                ->whereIn('strike_price', $strikesList)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->get();
        }

        // 5. Fallback latest prices from ohlc_quotes
        $fallbackPrices = [];
        if ($instruments->isNotEmpty()) {
            $instKeys = $instruments->pluck('instrument_key')->toArray();
            $latestQuotes = DB::table($tableName)
                ->select('instrument_key', 'close', 'ts_at')
                ->whereIn('instrument_key', $instKeys)
                ->orderByDesc('id')
                ->get()
                ->unique('instrument_key');

            foreach ($latestQuotes as $quote) {
                $fallbackPrices[$quote->instrument_key] = (float) $quote->close;
            }
        }

        // Structure strike items with CE & PE details
        $instrumentsMap = [];
        foreach ($instruments as $inst) {
            $strike = (int) $inst->strike_price;
            $type   = strtoupper($inst->instrument_type);
            $instrumentsMap[$strike][$type] = [
                'instrument_key' => $inst->instrument_key,
                'trading_symbol' => $inst->trading_symbol,
                'lot_size'       => $inst->lot_size,
                'fallback_price' => $fallbackPrices[$inst->instrument_key] ?? 0,
            ];
        }

        // 6. Current Month Future contract & price
        $currentFutExpiry = DB::table('nse_expiries')
            ->where('trading_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->where('is_current', 1)
            ->first();

        $futInstrument = null;
        if ($currentFutExpiry) {
            $futInstrument = DB::table('instruments')
                ->where('name', $symbol)
                ->where('instrument_type', 'FUT')
                ->where('expiry', $currentFutExpiry->expiry)
                ->first();
        }
        if (!$futInstrument) {
            $futInstrument = DB::table('instruments')
                ->where('name', $symbol)
                ->where('instrument_type', 'FUT')
                ->orderBy('expiry')
                ->first();
        }

        $futureKey    = $futInstrument ? $futInstrument->instrument_key : null;
        $futureSymbol = $futInstrument ? $futInstrument->trading_symbol : "{$symbol} FUT";
        $futurePrice  = 0;

        if ($futureKey) {
            $latestFutQuote = DB::table($tableName)
                ->where('instrument_key', $futureKey)
                ->orderByDesc('id')
                ->first();
            if ($latestFutQuote) {
                $futurePrice = (float) $latestFutQuote->close;
            }
        }
        if (!$futurePrice && $indexSpot > 0) {
            $futurePrice = $indexSpot;
        }

        $allInstrumentKeys = [$indexKey]; // Add index key to stream live index spot price
        if ($futureKey) {
            $allInstrumentKeys[] = $futureKey; // Stream live future price
        }
        $strikesData = [];
        foreach ($strikesList as $strike) {
            $ceData = $instrumentsMap[$strike]['CE'] ?? null;
            $peData = $instrumentsMap[$strike]['PE'] ?? null;

            if ($ceData && !empty($ceData['instrument_key'])) {
                $allInstrumentKeys[] = $ceData['instrument_key'];
            }
            if ($peData && !empty($peData['instrument_key'])) {
                $allInstrumentKeys[] = $peData['instrument_key'];
            }

            $strikesData[] = [
                'strike'   => $strike,
                'is_atm'   => ($strike === $atmStrike),
                'offset'   => ($strike - $atmStrike) / $strikeStep,
                'ce'       => [
                    'instrument_key' => $ceData['instrument_key'] ?? null,
                    'trading_symbol' => $ceData['trading_symbol'] ?? "{$symbol} {$strike} CE",
                    'price'          => $ceData['fallback_price'] ?? 0,
                ],
                'pe'       => [
                    'instrument_key' => $peData['instrument_key'] ?? null,
                    'trading_symbol' => $peData['trading_symbol'] ?? "{$symbol} {$strike} PE",
                    'price'          => $peData['fallback_price'] ?? 0,
                ],
            ];
        }

        return [
            'symbol'             => $symbol,
            'workingDate'        => $workingDate,
            'currentExpiry'      => $currentExpiry ? $currentExpiry->expiry_date : null,
            'nextExpiry'         => $nextExpiry ? $nextExpiry->expiry_date : null,
            'currentMidPoint'    => $currentMidPoint,
            'nextMidPoint'       => $nextMidPoint,
            'detectedAtm'        => $defaultAtmStrike,
            'atmStrike'          => $atmStrike,
            'indexOpen'          => $indexOpen,
            'indexSpot'          => $indexSpot,
            'indexClose'         => $indexClose,
            'indexKey'           => $indexKey,
            'futurePrice'        => $futurePrice,
            'futureKey'          => $futureKey,
            'futureSymbol'       => $futureSymbol,
            'range'              => $range,
            'strikeStep'         => $strikeStep,
            'strikesData'        => $strikesData,
            'allInstrumentKeys'  => array_values(array_unique($allInstrumentKeys)),
            'updatedAt'          => now()->format('d M Y, h:i:s A'),
        ];
    }
}
