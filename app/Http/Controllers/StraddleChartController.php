<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StraddleChartController extends Controller
{
    /**
     * Color palette for distinct strike lines.
     */
    protected array $strikeColors = [
        ['border' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)', 'name' => 'Emerald'],
        ['border' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.1)',  'name' => 'Cyan'],
        ['border' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.1)', 'name' => 'Violet'],
        ['border' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)', 'name' => 'Amber'],
        ['border' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.1)', 'name' => 'Pink'],
        ['border' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.1)', 'name' => 'Blue'],
        ['border' => '#f97316', 'bg' => 'rgba(249, 115, 22, 0.1)', 'name' => 'Orange'],
        ['border' => '#14b8a6', 'bg' => 'rgba(20, 184, 166, 0.1)', 'name' => 'Teal'],
    ];

    /**
     * Straddle Chart Main View.
     */
    public function index(Request $request)
    {
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $selectedSymbol = $request->input('symbol', 'NIFTY');

        // 1. Available Dates
        $datesFromOhlc = DB::table('ohlc_quotes')
            ->selectRaw('DATE(ts_at) as d')
            ->distinct()
            ->orderByDesc('d')
            ->limit(5)
            ->pluck('d')
            ->toArray();

        $availableDates = collect(array_merge([$today], $datesFromOhlc))
            ->filter()
            ->unique()
            ->values();

        $selectedDate = $request->input('date', $today);

        // 2. Available Expiries for Selected Date
        $expiries = DB::table('ohlc_quotes')
            ->whereDate('ts_at', $selectedDate)
            ->where('trading_symbol', $selectedSymbol)
            ->select('expiry_date')
            ->distinct()
            ->orderBy('expiry_date')
            ->pluck('expiry_date');

        if ($expiries->isEmpty()) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', $selectedSymbol)
                ->where('instrument_type', 'OPT')
                ->where('expiry_date', '>=', $selectedDate)
                ->orderBy('expiry_date')
                ->pluck('expiry_date');
        }

        $selectedExpiry = $request->input('expiry', $expiries->first() ?? $selectedDate);

        // 3. Resolve Current Spot and ATM Strike
        $underlyingKey = match (strtoupper($selectedSymbol)) {
            'BANKNIFTY' => 'NSE_INDEX|Nifty Bank',
            'FINNIFTY'  => 'NSE_INDEX|Nifty Fin Service',
            default     => 'NSE_INDEX|Nifty 50',
        };

        $latestRecord = DB::table('option_chains')
            ->where('underlying_key', $underlyingKey)
            ->whereDate('captured_at', $selectedDate)
            ->orderByDesc('captured_at')
            ->first(['underlying_spot_price']);

        if (!$latestRecord) {
            $latestRecord = DB::table('option_chains')
                ->whereDate('captured_at', $selectedDate)
                ->orderByDesc('captured_at')
                ->first(['underlying_spot_price']);
        }

        $spot = (float) ($latestRecord?->underlying_spot_price ?? 22700);
        $strikeStep = in_array(strtoupper($selectedSymbol), ['BANKNIFTY', 'SENSEX']) ? 100 : 50;
        $atmStrike = (int) (round($spot / $strikeStep) * $strikeStep);

        // 4. Available Strikes for Dropdown Picker
        $allStrikes = DB::table('ohlc_quotes')
            ->whereDate('ts_at', $selectedDate)
            ->where('expiry_date', $selectedExpiry)
            ->where('trading_symbol', $selectedSymbol)
            ->select('strike_price')
            ->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (int) $s)
            ->filter(fn($s) => $s > 0 && ($s % $strikeStep === 0))
            ->values();

        if ($allStrikes->isEmpty()) {
            $allStrikes = DB::table('option_chains')
                ->whereDate('captured_at', $selectedDate)
                ->where('expiry', $selectedExpiry)
                ->select('strike_price')
                ->distinct()
                ->orderBy('strike_price')
                ->pluck('strike_price')
                ->map(fn($s) => (int) $s)
                ->filter(fn($s) => $s > 0 && ($s % $strikeStep === 0))
                ->values();
        }

        if ($allStrikes->isEmpty()) {
            $allStrikes = DB::table('ohlc_quotes')
                ->whereDate('ts_at', $selectedDate)
                ->where('trading_symbol', $selectedSymbol)
                ->select('strike_price')
                ->distinct()
                ->orderBy('strike_price')
                ->pluck('strike_price')
                ->map(fn($s) => (int) $s)
                ->filter(fn($s) => $s > 0 && ($s % $strikeStep === 0))
                ->values();
        }

        if ($allStrikes->isEmpty()) {
            // Generate standard strikes around ATM
            $allStrikes = collect(range($atmStrike - (20 * $strikeStep), $atmStrike + (20 * $strikeStep), $strikeStep));
        }

        // 5. Default Selected Strikes (ATM by default)
        $defaultStrikes = $request->has('strikes')
            ? (is_array($request->input('strikes')) ? $request->input('strikes') : explode(',', $request->input('strikes')))
            : [$atmStrike];

        $defaultStrikes = array_map('intval', array_filter($defaultStrikes));
        if (empty($defaultStrikes)) {
            $defaultStrikes = [$atmStrike];
        }

        return view('straddle-chart.index', compact(
            'today',
            'selectedDate',
            'availableDates',
            'selectedSymbol',
            'expiries',
            'selectedExpiry',
            'spot',
            'atmStrike',
            'strikeStep',
            'allStrikes',
            'defaultStrikes'
        ));
    }

    /**
     * AJAX endpoint to fetch combined straddle line data and VWAP.
     */
    public function getData(Request $request)
    {
        $symbol   = $request->input('symbol', 'NIFTY');
        $date     = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $expiry   = $request->input('expiry');
        $showVwap = filter_var($request->input('show_vwap', true), FILTER_VALIDATE_BOOLEAN);

        $strikesInput = $request->input('strikes', []);
        if (is_string($strikesInput)) {
            $strikesInput = explode(',', $strikesInput);
        }
        $strikes = array_map('intval', array_filter($strikesInput));

        if (empty($strikes)) {
            return response()->json(['error' => 'No strikes specified'], 422);
        }

        // Format strikes as decimal strings for database matching ('22700.00' and '22700')
        $strikeStrings = [];
        foreach ($strikes as $s) {
            $strikeStrings[] = number_format($s, 2, '.', '');
            $strikeStrings[] = (string) $s;
        }

        // Query ohlc_quotes
        $rows = DB::table('ohlc_quotes')
            ->whereDate('ts_at', $date)
            ->where('expiry_date', $expiry)
            ->where('trading_symbol', $symbol)
            ->whereIn('strike_price', $strikeStrings)
            ->orderBy('ts_at')
            ->get(['ts_at', 'strike_price', 'instrument_type', 'open', 'high', 'low', 'close', 'volume', 'last_price']);

        // Fallback to option_chains if ohlc_quotes has no data
        if ($rows->isEmpty()) {
            $rows = DB::table('option_chains')
                ->whereDate('captured_at', $date)
                ->where('expiry', $expiry)
                ->whereIn('strike_price', $strikeStrings)
                ->orderBy('captured_at')
                ->get([
                    'captured_at as ts_at',
                    'strike_price',
                    'option_type as instrument_type',
                    'ltp as close',
                    'volume'
                ]);
        }

        if ($rows->isEmpty()) {
            return response()->json([
                'success'  => false,
                'message'  => "No intraday data found for Date: {$date} and Expiry: {$expiry}",
                'labels'   => [],
                'datasets' => [],
            ]);
        }

        // Group rows by Timestamp -> Strike -> CE/PE
        $grouped = [];
        $uniqueTimestamps = [];

        foreach ($rows as $r) {
            $timeLabel = substr($r->ts_at, 11, 5); // '09:15'
            $strikeInt = (int) $r->strike_price;
            $type      = strtoupper($r->instrument_type);

            $price = (float) ($r->close > 0 ? $r->close : ($r->last_price ?? 0));
            $vol   = (int) ($r->volume ?? 0);

            $grouped[$timeLabel][$strikeInt][$type] = [
                'price'  => $price,
                'volume' => $vol,
            ];

            $uniqueTimestamps[$timeLabel] = true;
        }

        ksort($uniqueTimestamps);
        $timeLabels = array_keys($uniqueTimestamps);

        $datasets = [];
        $strikeSummaries = [];

        foreach ($strikes as $idx => $strike) {
            $color = $this->strikeColors[$idx % count($this->strikeColors)];

            $cumPV   = 0.0;
            $cumVol  = 0;
            $avgData = [];
            $vwapData = [];

            $lastKnownPrice = null;
            $openPrice = null;

            foreach ($timeLabels as $time) {
                $ce = $grouped[$time][$strike]['CE'] ?? null;
                $pe = $grouped[$time][$strike]['PE'] ?? null;

                if ($ce && $pe && $ce['price'] > 0 && $pe['price'] > 0) {
                    $combinedAvg = round(($ce['price'] + $pe['price']) / 2.0, 2);
                    $totalVol    = $ce['volume'] + $pe['volume'];

                    $cumPV  += ($combinedAvg * $totalVol);
                    $cumVol += $totalVol;
                    $vwap = $cumVol > 0 ? round($cumPV / $cumVol, 2) : $combinedAvg;

                    $avgData[]  = $combinedAvg;
                    $vwapData[] = $vwap;

                    $lastKnownPrice = $combinedAvg;
                    if ($openPrice === null) {
                        $openPrice = $combinedAvg;
                    }
                } else {
                    // Carry forward previous price if 1-min bar is missing
                    $avgData[]  = $lastKnownPrice;
                    $vwapData[] = $cumVol > 0 ? round($cumPV / $cumVol, 2) : $lastKnownPrice;
                }
            }

            // Summary metadata for strike badge
            $currentVal = $lastKnownPrice ?? 0;
            $change = ($openPrice !== null) ? round($currentVal - $openPrice, 2) : 0;
            $changePct = ($openPrice > 0) ? round(($change / $openPrice) * 100, 2) : 0;

            $strikeSummaries[$strike] = [
                'strike'       => $strike,
                'color'        => $color['border'],
                'current_avg'  => $currentVal,
                'open_avg'     => $openPrice ?? 0,
                'change'       => $change,
                'change_pct'   => $changePct,
            ];

            // 1. Combined Straddle Average Line Dataset
            $datasets[] = [
                'type'             => 'line',
                'label'            => "{$strike} Straddle Avg",
                'strike'           => $strike,
                'data'             => $avgData,
                'borderColor'      => $color['border'],
                'backgroundColor'  => $color['bg'],
                'borderWidth'      => 2.5,
                'pointRadius'      => 0,
                'pointHoverRadius' => 4,
                'tension'          => 0.15,
                'isVwap'           => false,
            ];

            // 2. VWAP Line Dataset
            if ($showVwap) {
                $datasets[] = [
                    'type'             => 'line',
                    'label'            => "{$strike} VWAP",
                    'strike'           => $strike,
                    'data'             => $vwapData,
                    'borderColor'      => $color['border'],
                    'borderWidth'      => 1.5,
                    'borderDash'       => [4, 4],
                    'pointRadius'      => 0,
                    'pointHoverRadius' => 0,
                    'fill'             => false,
                    'isVwap'           => true,
                ];
            }
        }

        return response()->json([
            'success'   => true,
            'labels'    => $timeLabels,
            'datasets'  => $datasets,
            'summaries' => $strikeSummaries,
        ]);
    }
}
