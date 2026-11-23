<?php

namespace App\Http\Controllers;

use App\Models\OrderFlowTradedVolume;
use App\Services\TimeframeResampler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NiftyMultiTimeframeMonitorController extends Controller
{
    /**
     * Unified NIFTY 50 Command Center with Multi-Timeframe Capabilities:
     * Supports 1m, 3m, 5m, 15m, 30m, 60m, and 1 Day durations across all 4 quadrants.
     */
    public function index(Request $request): View
    {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        // 1. Fetch available trading dates efficiently from nse_working_days (<1ms)
        $availableDates = [];
        if (Schema::hasTable('nse_working_days')) {
            $availableDates = DB::table('nse_working_days')
                ->where('working_date', '<=', $todayStr)
                ->orderByDesc('working_date')
                ->limit(20)
                ->pluck('working_date')
                ->toArray();
        }

        if (empty($availableDates)) {
            $availableDates = [$todayStr];
        }

        // Fast resolution of latest date with quotes
        $latestQuoteDate = null;
        try {
            $latestTs = DB::table('ohlc_5m_quotes')
                ->where('instrument_key', 'NSE_INDEX|Nifty 50')
                ->orderByDesc('ts')
                ->value('ts_at');
            if ($latestTs) {
                $latestQuoteDate = substr($latestTs, 0, 10);
            }
        } catch (\Throwable $e) {
            $latestQuoteDate = null;
        }

        if ($latestQuoteDate && !in_array($latestQuoteDate, $availableDates)) {
            array_unshift($availableDates, $latestQuoteDate);
            $availableDates = array_values(array_unique($availableDates));
            rsort($availableDates);
        }

        $currentWorkingDate = null;
        if (Schema::hasTable('nse_working_days')) {
            $currentWorkingDate = DB::table('nse_working_days')
                ->where('current', 1)
                ->value('working_date');
        }

        $defaultDate = $currentWorkingDate ?: ($availableDates[0] ?? $todayStr);
        $selectedDate = $request->input('date', $defaultDate);

        // 2. Fetch available Expiries for NIFTY
        $expiries = [];
        if ($selectedDate < $todayStr) {
            if (Schema::hasTable('option_chains_history')) {
                $pastExpiries = DB::table(DB::raw("option_chains_history USE INDEX (idx_och_sym_exp_cap)"))
                    ->where('trading_symbol', 'NIFTY')
                    ->where('captured_at', '>=', "{$selectedDate} 09:00:00")
                    ->where('captured_at', '<=', "{$selectedDate} 15:35:00")
                    ->distinct()
                    ->orderBy('expiry')
                    ->pluck('expiry')
                    ->toArray();
                if (!empty($pastExpiries)) {
                    $expiries = $pastExpiries;
                }
            }

            if (empty($expiries) && Schema::hasTable('expired_expiries')) {
                $expiries = DB::table('expired_expiries')
                    ->where('underlying_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $selectedDate)
                    ->orderBy('expiry_date')
                    ->limit(6)
                    ->pluck('expiry_date')
                    ->toArray();
            }
        }

        if (empty($expiries) && Schema::hasTable('nse_expiries')) {
            $expiries = DB::table('nse_expiries')
                ->where('trading_symbol', 'NIFTY')
                ->where('instrument_type', 'OPT')
                ->orderBy('expiry_date')
                ->pluck('expiry_date')
                ->toArray();
        }

        if (empty($expiries) && Schema::hasTable('option_chains')) {
            $expiries = DB::table('option_chains')
                ->where('underlying_key', 'like', '%Nifty%')
                ->select('expiry')
                ->distinct()
                ->orderBy('expiry')
                ->pluck('expiry')
                ->toArray();
        }

        $defaultExpiry = null;
        if (!empty($expiries)) {
            foreach ($expiries as $exp) {
                if ($exp >= $selectedDate) {
                    $defaultExpiry = $exp;
                    break;
                }
            }
            if (!$defaultExpiry) {
                $defaultExpiry = $expiries[0];
            }
        } else {
            $defaultExpiry = $selectedDate;
        }

        $selectedExpiry = $request->input('expiry', $defaultExpiry);

        // 3. Multi-Timeframe options
        $availableTimeframes = [
            '1m'  => ['label' => '1M',  'minutes' => 1],
            '3m'  => ['label' => '3M',  'minutes' => 3],
            '5m'  => ['label' => '5M',  'minutes' => 5],
            '15m' => ['label' => '15M', 'minutes' => 15],
            '30m' => ['label' => '30M', 'minutes' => 30],
            '60m' => ['label' => '1H',  'minutes' => 60],
            '1D'  => ['label' => '1D',  'minutes' => 375],
        ];

        $rawTf = $request->input('timeframe', $request->input('interval', '5m'));
        $tfMinutes = TimeframeResampler::normalizeInterval($rawTf);
        $selectedTimeframe = match ($tfMinutes) {
            1       => '1m',
            3       => '3m',
            15      => '15m',
            30      => '30m',
            60      => '60m',
            375     => '1D',
            default => '5m',
        };

        // 4. Institutional Quant S/R Levels (Nifty Spot)
        $quantPlaybook = null;
        try {
            $reportService = app(\App\Services\OrderFlow\OrderFlowCumulativeReportService::class);
            $reportData = $reportService->generateReport($selectedDate);
            $quantPlaybook = $reportData['tomorrow_playbook'] ?? null;
        } catch (\Throwable $e) {
            $quantPlaybook = null;
        }

        return view('nifty-unified.multi-timeframe', compact(
            'selectedDate',
            'availableDates',
            'expiries',
            'selectedExpiry',
            'quantPlaybook',
            'availableTimeframes',
            'selectedTimeframe',
            'tfMinutes'
        ));
    }

    /**
     * Get Institutional Clustered Order Flow Support & Resistance Levels (Yesterday & Today)
     */
    public function getOrderFlowLevels(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $limit = max(1, min(10, (int) $request->input('limit', 3)));
        $clusterPts = (float) $request->input('cluster', ($symbol === 'BANKNIFTY' ? 10.0 : 5.0));
        $rankBy = strtolower($request->input('rank_by', 'cumulative')); // 'cumulative' (default) or 'peak'

        // 1. Resolve Reference Spot Price for Deviation Filtering (allow client to pass current live spot)
        $refPrice = (float) $request->input('spot', 0.0);
        if ($refPrice <= 0) {
            try {
                $instKey = ($symbol === 'BANKNIFTY') ? 'NSE_INDEX|Nifty Bank' : 'NSE_INDEX|Nifty 50';
                $quote = DB::table('ohlc_5m_quotes')
                    ->where('instrument_key', $instKey)
                    ->where('ts_at', '>=', "{$date} 09:15:00")
                    ->where('ts_at', '<=', "{$date} 15:30:00")
                    ->orderBy('ts_at', 'desc')
                    ->first(['open', 'close']);
                if ($quote) {
                    $refPrice = (float) ($quote->close ?: $quote->open);
                }
            } catch (\Throwable $e) {
                $refPrice = ($symbol === 'BANKNIFTY') ? 57000.0 : 25000.0;
            }
        }

        if ($refPrice <= 0) {
            $refPrice = ($symbol === 'BANKNIFTY') ? 57000.0 : 25000.0;
        }

        // 2. Resolve Previous Trading Date
        $prevDate = null;
        if (Schema::hasTable('nse_working_days')) {
            $prevDate = DB::table('nse_working_days')
                ->where('working_date', '<', $date)
                ->orderByDesc('working_date')
                ->value('working_date');
        }
        if (!$prevDate) {
            $prevDate = Carbon::parse($date)->subDay()->toDateString();
        }

        // 2b. Calculate Latest Future Price & Basis Spread (Future - Spot)
        $futurePrice = 0.0;
        if (Schema::hasTable('order_flow_traded_volumes')) {
            $latestTrade = DB::table('order_flow_traded_volumes')
                ->where('trade_date', $date)
                ->where('symbol', $symbol)
                ->orderByDesc('id')
                ->first(['price']);
            if (!$latestTrade) {
                $latestTrade = DB::table('order_flow_traded_volumes')
                    ->where('trade_date', $prevDate)
                    ->where('symbol', $symbol)
                    ->orderByDesc('id')
                    ->first(['price']);
            }
            if ($latestTrade) {
                $futurePrice = (float) $latestTrade->price;
            }
        }
        $basisSpread = ($futurePrice > 0 && $refPrice > 0) ? round($futurePrice - $refPrice, 1) : 0.0;

        // 3. Yesterday Clustered S/R
        $ydAsks = [];
        $ydBids = [];
        if (Schema::hasTable('order_flow_traded_volumes')) {
            $ydAsks = $this->clusterLevels(OrderFlowTradedVolume::forDate($prevDate)->forSymbol($symbol)->ask(), $refPrice, $clusterPts, $limit, 'YD RES', $rankBy, $basisSpread);
            $ydBids = $this->clusterLevels(OrderFlowTradedVolume::forDate($prevDate)->forSymbol($symbol)->bid(), $refPrice, $clusterPts, $limit, 'YD SUP', $rankBy, $basisSpread);
        }

        // 4. Today Live Clustered S/R
        $tdAsks = [];
        $tdBids = [];
        if (Schema::hasTable('order_flow_traded_volumes')) {
            $tdAsks = $this->clusterLevels(OrderFlowTradedVolume::forDate($date)->forSymbol($symbol)->ask(), $refPrice, $clusterPts, $limit, 'TD RES', $rankBy, $basisSpread);
            $tdBids = $this->clusterLevels(OrderFlowTradedVolume::forDate($date)->forSymbol($symbol)->bid(), $refPrice, $clusterPts, $limit, 'TD SUP', $rankBy, $basisSpread);
        }

        return response()->json([
            'success'      => true,
            'date'         => $date,
            'prev_date'    => $prevDate,
            'symbol'       => $symbol,
            'ref_price'    => $refPrice,
            'future_price' => $futurePrice,
            'basis_spread' => $basisSpread,
            'rank_by'      => $rankBy,
            'yesterday'    => [
                'asks' => $ydAsks,
                'bids' => $ydBids,
            ],
            'today'        => [
                'asks' => $tdAsks,
                'bids' => $tdBids,
            ],
        ]);
    }

    /**
     * Get Multi-Day Top 1 Order Flow Support & Resistance Levels (Past N Days)
     */
    public function getMultiDayOrderFlowLevels(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $symbol = strtoupper($request->input('symbol', 'NIFTY'));
        $daysCount = max(1, min(10, (int) $request->input('days', 5)));
        $clusterPts = (float) $request->input('cluster', ($symbol === 'BANKNIFTY' ? 10.0 : 5.0));
        $rankBy = strtolower($request->input('rank_by', 'cumulative')); // 'cumulative' or 'peak'
        $refPrice = (float) $request->input('spot', 0.0);

        if ($refPrice <= 0) {
            $refPrice = ($symbol === 'BANKNIFTY') ? 57000.0 : 25200.0;
        }

        // Cache for 60 seconds (for today's session)
        $cacheKey = "multiday_of_levels_{$symbol}_{$date}_{$daysCount}_{$rankBy}_" . round($refPrice, -1);
        $result = Cache::remember($cacheKey, 60, function () use ($date, $symbol, $daysCount, $clusterPts, $rankBy, $refPrice) {
            // Find past trading dates
            $pastDates = [];
            if (Schema::hasTable('nse_working_days')) {
                $pastDates = DB::table('nse_working_days')
                    ->where('working_date', '<', $date)
                    ->orderByDesc('working_date')
                    ->limit($daysCount)
                    ->pluck('working_date')
                    ->toArray();
            }

            if (count($pastDates) < $daysCount && Schema::hasTable('order_flow_traded_volumes')) {
                $existingDates = DB::table('order_flow_traded_volumes')
                    ->where('trade_date', '<', $date)
                    ->where('symbol', $symbol)
                    ->distinct()
                    ->orderByDesc('trade_date')
                    ->limit($daysCount)
                    ->pluck('trade_date')
                    ->map(fn($d) => Carbon::parse($d)->toDateString())
                    ->toArray();
                $pastDates = array_values(array_unique(array_merge($pastDates, $existingDates)));
                rsort($pastDates);
                $pastDates = array_slice($pastDates, 0, $daysCount);
            }

            // Basis Spread: Calculate between latest Future trade and Spot
            $futurePrice = 0.0;
            if (Schema::hasTable('order_flow_traded_volumes')) {
                $latestTrade = DB::table('order_flow_traded_volumes')
                    ->where('trade_date', '<=', $date)
                    ->where('symbol', $symbol)
                    ->orderByDesc('id')
                    ->first(['price']);
                if ($latestTrade) {
                    $futurePrice = (float) $latestTrade->price;
                }
            }
            $basisSpread = ($futurePrice > 0 && $refPrice > 0) ? round($futurePrice - $refPrice, 1) : 0.0;

            $days = [];
            foreach ($pastDates as $idx => $d) {
                $cDate = Carbon::parse($d);
                $formattedDate = $cDate->format('d M');
                $dayCode = ($idx === 0) ? 'YD' : "D-" . ($idx + 1);

                $askLevels = $this->clusterLevels(
                    OrderFlowTradedVolume::forDate($d)->forSymbol($symbol)->ask(),
                    $refPrice,
                    $clusterPts,
                    1,
                    "{$formattedDate} RES",
                    $rankBy,
                    $basisSpread
                );

                $bidLevels = $this->clusterLevels(
                    OrderFlowTradedVolume::forDate($d)->forSymbol($symbol)->bid(),
                    $refPrice,
                    $clusterPts,
                    1,
                    "{$formattedDate} SUP",
                    $rankBy,
                    $basisSpread
                );

                $days[] = [
                    'date'           => $d,
                    'formatted_date' => $formattedDate,
                    'day_code'       => $dayCode,
                    'day_offset'     => $idx + 1,
                    'ask'            => $askLevels[0] ?? null,
                    'bid'            => $bidLevels[0] ?? null,
                ];
            }

            return [
                'success'      => true,
                'date'         => $date,
                'symbol'       => $symbol,
                'ref_price'    => $refPrice,
                'future_price' => $futurePrice,
                'basis_spread' => $basisSpread,
                'rank_by'      => $rankBy,
                'days'         => $days,
            ];
        });

        return response()->json($result);
    }

    /**
     * Volume-Weighted Clustering of Order Flow Traded Volume prints
     */
    protected function clusterLevels($query, float $refPrice, float $clusterDistance, int $limit, string $prefix, string $rankBy = 'cumulative', float $basisSpread = 0.0): array
    {
        $records = $query->get(['price', 'quantity', 'trade_time']);
        if ($records->isEmpty()) return [];

        $sorted = $records->sortByDesc('quantity')->values();
        $clusters = [];
        $maxDeviation = (str_contains($prefix, 'BANK') || $refPrice > 40000) ? 0.05 : 0.035;

        foreach ($sorted as $r) {
            $p = (float) $r->price;
            $q = (int) $r->quantity;
            $t = (string) $r->trade_time;

            if ($refPrice > 0 && abs($p - $refPrice) > ($refPrice * $maxDeviation)) {
                continue;
            }

            $matched = null;
            foreach ($clusters as $idx => $c) {
                if (abs($c['price'] - $p) <= $clusterDistance) {
                    $matched = $idx;
                    break;
                }
            }

            if ($matched !== null) {
                $totalQ = $clusters[$matched]['qty'] + $q;
                $clusters[$matched]['price'] = round((($clusters[$matched]['price'] * $clusters[$matched]['qty']) + ($p * $q)) / $totalQ, 1);
                $clusters[$matched]['qty'] = $totalQ;
                $clusters[$matched]['peak_qty'] = max($clusters[$matched]['peak_qty'], $q);
                $clusters[$matched]['count']++;
            } else {
                $clusters[] = [
                    'price'    => round($p, 1),
                    'qty'      => $q,
                    'peak_qty' => $q,
                    'count'    => 1,
                    'time'     => $t,
                ];
            }
        }

        if ($rankBy === 'peak') {
            usort($clusters, fn($a, $b) => $b['peak_qty'] <=> $a['peak_qty']);
        } else {
            usort($clusters, fn($a, $b) => $b['qty'] <=> $a['qty']);
        }
        $sliced = array_slice($clusters, 0, $limit);

        return array_map(function ($c) use ($prefix, $rankBy, $basisSpread) {
            $kQty = $c['qty'] >= 1000 ? round($c['qty'] / 1000, 1) . 'k' : (string) $c['qty'];
            $kPeak = $c['peak_qty'] >= 1000 ? round($c['peak_qty'] / 1000, 1) . 'k' : (string) $c['peak_qty'];
            $fmtRaw = number_format($c['price'], ($c['price'] == round($c['price'])) ? 0 : 1);
            $calibrated = round($c['price'] - $basisSpread, 1);
            $fmtCalibrated = number_format($calibrated, ($calibrated == round($calibrated)) ? 0 : 1);

            $c['raw_price'] = $c['price'];
            $c['calibrated_price'] = $calibrated;
            $c['basis_spread'] = $basisSpread;

            if ($rankBy === 'peak') {
                $c['label'] = "{$prefix} ₹{$fmtCalibrated} [Peak: {$kPeak}]";
                $c['raw_label'] = "{$prefix} (FUT) ₹{$fmtRaw} [Peak: {$kPeak}]";
            } else {
                $c['label'] = "{$prefix} ₹{$fmtCalibrated} [{$kQty}]";
                $c['raw_label'] = "{$prefix} (FUT) ₹{$fmtRaw} [{$kQty}]";
            }
            return $c;
        }, $sliced);
    }

    /**
     * Save full page screenshot image (PNG) to D:\OneDrive - Buffercode\0iohlc\{date}\{timestamp}.png
     */
    public function saveScreenshot(Request $request): JsonResponse
    {
        $imageData = $request->input('image');
        if (empty($imageData)) {
            return response()->json(['success' => false, 'error' => 'No image data provided'], 400);
        }

        // Handle Base64 Data URL (data:image/png;base64,...)
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $ext = strtolower($type[1]);
            if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $ext = 'png';
            }
        } else {
            $ext = 'png';
        }

        $decodedImage = base64_decode($imageData);
        if ($decodedImage === false) {
            return response()->json(['success' => false, 'error' => 'Invalid base64 image data'], 400);
        }

        $now = Carbon::now('Asia/Kolkata');
        $date = $request->input('date', $now->toDateString());
        $safeDate = preg_replace('/[^0-9\-]/', '', (string)$date) ?: $now->toDateString();

        $timeStr = $request->input('time', $now->format('H-i-s'));
        $safeTime = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace([':', ' '], ['-', '_'], (string)$timeStr)) ?: $now->format('H-i-s');

        $baseDir = "D:\\OneDrive - Buffercode\\0iohlc";
        $targetDir = "{$baseDir}\\{$safeDate}";

        try {
            if (!File::isDirectory($targetDir)) {
                File::makeDirectory($targetDir, 0755, true, true);
            }

            $filename = "{$safeTime}.{$ext}";
            $fullPath = "{$targetDir}\\{$filename}";

            $counter = 1;
            while (File::exists($fullPath)) {
                $filename = "{$safeTime}_{$counter}.{$ext}";
                $fullPath = "{$targetDir}\\{$filename}";
                $counter++;
            }

            File::put($fullPath, $decodedImage);

            return response()->json([
                'success'   => true,
                'path'      => $fullPath,
                'filename'  => $filename,
                'directory' => $targetDir,
                'size'      => strlen($decodedImage),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Comprehensive Day AI Diagnosis Download (Markdown / JSON)
     * Provides full session autopsy data for any LLM (ChatGPT, Claude, Gemini).
     */
    public function downloadDayAiDiagnosis(Request $request)
    {
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $expiry = $request->input('expiry', null);
        $format = strtolower($request->input('format', 'md'));
        $download = $request->boolean('download', true);

        try {
            $diagnosisService = app(\App\Services\NiftyDayAiDiagnosisService::class);
            $data = $diagnosisService->getDiagnosisData($date, $expiry);

            if ($format === 'json') {
                $jsonStr = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($download) {
                    return response()->streamDownload(function () use ($jsonStr) {
                        echo $jsonStr;
                    }, "nifty-day-diagnosis-{$date}.json", [
                        'Content-Type' => 'application/json; charset=utf-8',
                    ]);
                }
                return response()->json($data);
            }

            // Default to Markdown
            $markdown = $diagnosisService->generateMarkdownReport($data);

            if ($download) {
                return response()->streamDownload(function () use ($markdown) {
                    echo $markdown;
                }, "nifty-day-diagnosis-{$date}.md", [
                    'Content-Type' => 'text/markdown; charset=utf-8',
                ]);
            }

            return response($markdown, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
