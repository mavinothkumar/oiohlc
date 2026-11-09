<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpiryOiVolumeAnalysisController extends Controller
{
    /**
     * Display the main Expiry Day-over-Day OI & Volume Analysis page.
     */
    public function index(Request $request)
    {
        $payload = $this->buildAnalysisPayload($request);

        return view('expiry-oi-volume.index', $payload);
    }

    /**
     * JSON API endpoint to fetch filtered comparison data via AJAX.
     */
    public function getData(Request $request)
    {
        $payload = $this->buildAnalysisPayload($request);

        return response()->json([
            'ok' => true,
            'data' => $payload,
        ]);
    }

    /**
     * JSON API endpoint to fetch multi-day progression for a single strike across the expiry.
     */
    public function getStrikeHistory(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'NIFTY'));
        $expiry = $request->query('expiry');
        $strike = (int) $request->query('strike');

        if (!$expiry || !$strike) {
            return response()->json(['ok' => false, 'message' => 'Expiry and Strike are required'], 422);
        }

        $tradingDates = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $expiry)
            ->select('quote_date')
            ->distinct()
            ->orderBy('quote_date', 'asc')
            ->pluck('quote_date')
            ->toArray();

        $rows = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $expiry)
            ->where('strike', (string)$strike)
            ->whereIn('option_type', ['CE', 'PE'])
            ->orderBy('quote_date', 'asc')
            ->get();

        $byDateAndType = [];
        foreach ($rows as $r) {
            $byDateAndType[$r->quote_date][strtoupper($r->option_type)] = $r;
        }

        // Fetch spot prices
        $spotRows = DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->whereIn('trading_date', $tradingDates)
            ->get()
            ->keyBy('trading_date');

        $history = [];
        $prevCe = null;
        $prevPe = null;

        foreach ($tradingDates as $idx => $date) {
            $ce = $byDateAndType[$date]['CE'] ?? null;
            $pe = $byDateAndType[$date]['PE'] ?? null;
            $spot = $spotRows->has($date) ? (float) $spotRows[$date]->index_close : null;
            $atmStrike = $spot ? (int)(round($spot / 50) * 50) : null;

            // CE calculations
            $ceClose = $ce ? (float)$ce->close : 0;
            $ceVol = $ce ? (int)$ce->volume : 0;
            $ceOi = $ce ? (int)$ce->open_interest : 0;
            $cePrevOi = $prevCe ? (int)$prevCe->open_interest : 0;
            $cePrevVol = $prevCe ? (int)$prevCe->volume : 0;
            $cePrevClose = $prevCe ? (float)$prevCe->close : 0;

            $ceOiDiff = $prevCe ? ($ceOi - $cePrevOi) : 0;
            $ceOiPct = ($cePrevOi > 0) ? round(($ceOiDiff / $cePrevOi) * 100, 2) : 0;
            $ceVolDiff = $prevCe ? ($ceVol - $cePrevVol) : 0;
            $ceVolPct = ($cePrevVol > 0) ? round(($ceVolDiff / $cePrevVol) * 100, 2) : 0;
            $cePriceDiff = $prevCe ? round($ceClose - $cePrevClose, 2) : 0;

            // PE calculations
            $peClose = $pe ? (float)$pe->close : 0;
            $peVol = $pe ? (int)$pe->volume : 0;
            $peOi = $pe ? (int)$pe->open_interest : 0;
            $pePrevOi = $prevPe ? (int)$prevPe->open_interest : 0;
            $pePrevVol = $prevPe ? (int)$prevPe->volume : 0;
            $pePrevClose = $prevPe ? (float)$prevPe->close : 0;

            $peOiDiff = $prevPe ? ($peOi - $pePrevOi) : 0;
            $peOiPct = ($pePrevOi > 0) ? round(($peOiDiff / $pePrevOi) * 100, 2) : 0;
            $peVolDiff = $prevPe ? ($peVol - $pePrevVol) : 0;
            $peVolPct = ($pePrevVol > 0) ? round(($peVolDiff / $pePrevVol) * 100, 2) : 0;
            $pePriceDiff = $prevPe ? round($peClose - $pePrevClose, 2) : 0;

            $ceBuildup = $this->classifyBuildup($cePriceDiff, $ceOiDiff);
            $peBuildup = $this->classifyBuildup($pePriceDiff, $peOiDiff);

            $history[] = [
                'day_number' => $idx + 1,
                'quote_date' => $date,
                'quote_date_formatted' => Carbon::parse($date)->format('d M Y (D)'),
                'spot_close' => $spot,
                'atm_strike' => $atmStrike,
                'moneyness_ce' => $atmStrike ? ($strike < $atmStrike ? 'ITM' : ($strike == $atmStrike ? 'ATM' : 'OTM')) : '-',
                'moneyness_pe' => $atmStrike ? ($strike > $atmStrike ? 'ITM' : ($strike == $atmStrike ? 'ATM' : 'OTM')) : '-',
                'ce' => [
                    'close' => $ceClose,
                    'price_diff' => $cePriceDiff,
                    'volume' => $ceVol,
                    'volume_diff' => $ceVolDiff,
                    'volume_pct' => $ceVolPct,
                    'oi' => $ceOi,
                    'oi_diff' => $ceOiDiff,
                    'oi_pct' => $ceOiPct,
                    'buildup' => $ceBuildup,
                ],
                'pe' => [
                    'close' => $peClose,
                    'price_diff' => $pePriceDiff,
                    'volume' => $peVol,
                    'volume_diff' => $peVolDiff,
                    'volume_pct' => $peVolPct,
                    'oi' => $peOi,
                    'oi_diff' => $peOiDiff,
                    'oi_pct' => $peOiPct,
                    'buildup' => $peBuildup,
                ],
                'straddle_close' => ($ceClose > 0 && $peClose > 0) ? round($ceClose + $peClose, 2) : null,
                'total_oi' => $ceOi + $peOi,
                'total_vol' => $ceVol + $peVol,
            ];

            $prevCe = $ce;
            $prevPe = $pe;
        }

        return response()->json([
            'ok' => true,
            'strike' => $strike,
            'expiry' => $expiry,
            'symbol' => $symbol,
            'history' => $history,
        ]);
    }

    /**
     * Internal method to build complete analysis payload.
     */
    private function buildAnalysisPayload(Request $request): array
    {
        $symbol = strtoupper($request->query('symbol', 'NIFTY'));
        $symbols = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'SENSEX', 'BANKEX'];

        // 1. Fetch all available expiries for symbol
        $expiryRows = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->select(
                'expiry_date',
                DB::raw('COUNT(DISTINCT quote_date) as day_count'),
                DB::raw('MIN(quote_date) as min_date'),
                DB::raw('MAX(quote_date) as max_date'),
                DB::raw('COUNT(*) as total_quotes')
            )
            ->groupBy('expiry_date')
            ->orderByDesc('expiry_date')
            ->get();

        $expiries = $expiryRows->map(function ($row) {
            return [
                'expiry_date' => $row->expiry_date,
                'formatted' => Carbon::parse($row->expiry_date)->format('d M Y'),
                'day_count' => (int) $row->day_count,
                'min_date' => $row->min_date,
                'max_date' => $row->max_date,
                'total_quotes' => (int) $row->total_quotes,
            ];
        })->values();

        if ($expiries->isEmpty()) {
            return $this->emptyResponse('No expiry data found for symbol ' . $symbol, $symbol, $symbols);
        }

        // 2. Select Expiry
        $selectedExpiry = $request->query('expiry', $expiries->first()['expiry_date']);
        $currentExpiryMeta = $expiries->firstWhere('expiry_date', $selectedExpiry) ?? $expiries->first();
        $selectedExpiry = $currentExpiryMeta['expiry_date'];

        // 3. Fetch all trading dates for this expiry in ascending chronological order
        $tradingDates = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $selectedExpiry)
            ->select('quote_date')
            ->distinct()
            ->orderBy('quote_date', 'asc')
            ->pluck('quote_date')
            ->toArray();

        if (empty($tradingDates)) {
            return $this->emptyResponse('No quote dates found for selected expiry.', $symbol, $symbols, $expiries);
        }

        // 4. Determine Selected Date and Previous Date
        $requestedDate = $request->query('date');
        $selectedDateIndex = 0;
        if ($requestedDate && in_array($requestedDate, $tradingDates)) {
            $selectedDateIndex = array_search($requestedDate, $tradingDates);
        } else {
            // Default to latest trading date for this expiry
            $selectedDateIndex = count($tradingDates) - 1;
        }

        $selectedDate = $tradingDates[$selectedDateIndex];
        $isFirstDayOfExpiry = ($selectedDateIndex === 0);

        // Previous Day: if Day 1, check if earlier quote date exists; else use index - 1
        $previousDate = null;
        if ($selectedDateIndex > 0) {
            $previousDate = $tradingDates[$selectedDateIndex - 1];
        } else {
            // Check if there is any prior day recorded in daily_ohlc_quotes before this date
            $priorDate = DB::table('daily_ohlc_quotes')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $selectedExpiry)
                ->where('quote_date', '<', $selectedDate)
                ->orderByDesc('quote_date')
                ->value('quote_date');
            $previousDate = $priorDate ?: null;
        }

        // Next Day: for backtesting future market reaction
        $nextDate = null;
        if ($selectedDateIndex < count($tradingDates) - 1) {
            $nextDate = $tradingDates[$selectedDateIndex + 1];
        } else {
            // Check if next trading day exists in daily_trend
            $nextDate = DB::table('daily_trend')
                ->where('symbol_name', $symbol)
                ->where('trading_date', '>', $selectedDate)
                ->orderBy('trading_date', 'asc')
                ->value('trading_date');
        }

        // 5. Fetch Spot Prices and Market Trends
        $currTrend = DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->where('trading_date', $selectedDate)
            ->first();

        $prevTrend = $previousDate ? DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->where('trading_date', $previousDate)
            ->first() : null;

        $nextTrend = $nextDate ? DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->where('trading_date', $nextDate)
            ->first() : null;

        $currSpot = $currTrend && $currTrend->index_close ? (float) $currTrend->index_close : null;
        $prevSpot = $prevTrend && $prevTrend->index_close ? (float) $prevTrend->index_close : null;

        $spotDiff = ($currSpot && $prevSpot) ? round($currSpot - $prevSpot, 2) : 0;
        $spotDiffPct = ($prevSpot && $prevSpot > 0) ? round(($spotDiff / $prevSpot) * 100, 2) : 0;

        // Spot ATM Strike
        $step = ($symbol === 'BANKNIFTY') ? 100 : 50;
        if ($currTrend && !empty($currTrend->strike)) {
            $atmStrike = (int) $currTrend->strike;
        } elseif ($currSpot) {
            $atmStrike = (int) (round($currSpot / $step) * $step);
        } else {
            $atmStrike = 24500;
        }

        // Next Day Market Reaction (Backtesting Result)
        $nextDayOutcome = null;
        if ($nextTrend && $currSpot) {
            $nextOpen = (float) ($nextTrend->current_day_index_open ?? $nextTrend->atm_index_open ?? $currSpot);
            $nextHigh = (float) ($nextTrend->index_high ?? $nextOpen);
            $nextLow = (float) ($nextTrend->index_low ?? $nextOpen);
            $nextClose = (float) ($nextTrend->index_close ?? $nextOpen);
            $nextChange = round($nextClose - $currSpot, 2);
            $nextChangePct = round(($nextChange / $currSpot) * 100, 2);
            $gapPts = round($nextOpen - $currSpot, 2);

            $nextDayOutcome = [
                'trading_date' => $nextDate,
                'formatted_date' => Carbon::parse($nextDate)->format('d M Y (D)'),
                'open' => $nextOpen,
                'high' => $nextHigh,
                'low' => $nextLow,
                'close' => $nextClose,
                'change_pts' => $nextChange,
                'change_pct' => $nextChangePct,
                'gap_pts' => $gapPts,
                'gap_type' => $gapPts > 20 ? 'Gap Up' : ($gapPts < -20 ? 'Gap Down' : 'Flat Open'),
                'direction' => $nextChange > 0 ? 'BULLISH (UP)' : ($nextChange < 0 ? 'BEARISH (DOWN)' : 'FLAT'),
            ];
        }

        // 6. Fetch Quotes for Current Day and Previous Day
        $currQuotes = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $selectedExpiry)
            ->where('quote_date', $selectedDate)
            ->get()
            ->keyBy(fn ($r) => ((int)$r->strike) . '_' . strtoupper($r->option_type));

        $prevQuotes = collect();
        if ($previousDate) {
            $prevQuotes = DB::table('daily_ohlc_quotes')
                ->where('symbol_name', $symbol)
                ->where('expiry_date', $selectedExpiry)
                ->where('quote_date', $previousDate)
                ->get()
                ->keyBy(fn ($r) => ((int)$r->strike) . '_' . strtoupper($r->option_type));
        }

        // All distinct strikes across both sets
        $allStrikes = $currQuotes->pluck('strike')
            ->concat($prevQuotes->pluck('strike'))
            ->map(fn ($s) => (int)$s)
            ->unique()
            ->sort()
            ->values();

        if ($allStrikes->isEmpty()) {
            return $this->emptyResponse('No strike data available for ' . $selectedDate, $symbol, $symbols, $expiries);
        }

        // Fallback ATM if not established yet
        if (!$currSpot && $allStrikes->isNotEmpty()) {
            $atmStrike = (int) $allStrikes->get((int)(count($allStrikes) / 2));
        }

        // 7. Filter Strikes based on Strike Range & Moneyness
        $strikeRangeFilter = $request->query('strike_range', '10'); // '5', '10', '15', '20', 'all'
        $customMinStrike = $request->query('min_strike');
        $customMaxStrike = $request->query('max_strike');
        $moneynessFilter = strtolower($request->query('moneyness', 'all')); // 'all', 'atm', 'otm', 'itm', 'surges'

        $filteredStrikes = $allStrikes;

        if ($customMinStrike && $customMaxStrike) {
            $filteredStrikes = $filteredStrikes->filter(fn ($s) => $s >= (int)$customMinStrike && $s <= (int)$customMaxStrike);
        } elseif (is_numeric($strikeRangeFilter)) {
            $rangeCount = (int) $strikeRangeFilter;
            $minBound = $atmStrike - ($rangeCount * $step);
            $maxBound = $atmStrike + ($rangeCount * $step);
            $filteredStrikes = $filteredStrikes->filter(fn ($s) => $s >= $minBound && $s <= $maxBound);
        }

        // 8. Build Strike-by-Strike Comparative Matrix & Aggregate Counters
        $rows = [];
        $totalCeOiCurr = 0;
        $totalCeOiPrev = 0;
        $totalPeOiCurr = 0;
        $totalPeOiPrev = 0;

        $totalCeVolCurr = 0;
        $totalCeVolPrev = 0;
        $totalPeVolCurr = 0;
        $totalPeVolPrev = 0;

        // Moneyness bucket tallies
        $moneynessShifts = [
            'atm' => ['ce_oi_diff' => 0, 'pe_oi_diff' => 0, 'ce_vol_diff' => 0, 'pe_vol_diff' => 0],
            'otm' => ['ce_oi_diff' => 0, 'pe_oi_diff' => 0, 'ce_vol_diff' => 0, 'pe_vol_diff' => 0],
            'itm' => ['ce_oi_diff' => 0, 'pe_oi_diff' => 0, 'ce_vol_diff' => 0, 'pe_vol_diff' => 0],
        ];

        // Track maximums for depth visual bars
        $maxCeOi = 1;
        $maxPeOi = 1;
        $maxCeVol = 1;
        $maxPeVol = 1;

        $anomaliesList = [];

        foreach ($filteredStrikes as $strike) {
            $ceCurr = $currQuotes->get($strike . '_CE');
            $cePrev = $prevQuotes->get($strike . '_CE');
            $peCurr = $currQuotes->get($strike . '_PE');
            $pePrev = $prevQuotes->get($strike . '_PE');

            // Moneyness classifications
            $ceMoneyness = ($strike < $atmStrike) ? 'ITM' : (($strike == $atmStrike) ? 'ATM' : 'OTM');
            $peMoneyness = ($strike > $atmStrike) ? 'ITM' : (($strike == $atmStrike) ? 'ATM' : 'OTM');

            // CE metrics
            $ceCloseCurr = $ceCurr ? (float) $ceCurr->close : 0;
            $ceClosePrev = $cePrev ? (float) $cePrev->close : ($ceCurr ? (float) $ceCurr->open : 0);
            $cePriceDiff = round($ceCloseCurr - $ceClosePrev, 2);
            $cePricePct = ($ceClosePrev > 0) ? round(($cePriceDiff / $ceClosePrev) * 100, 2) : 0;

            $ceVolCurr = $ceCurr ? (int) $ceCurr->volume : 0;
            $ceVolPrev = $cePrev ? (int) $cePrev->volume : 0;
            $ceVolDiff = $ceVolCurr - $ceVolPrev;
            $ceVolPct = ($ceVolPrev > 0) ? round(($ceVolDiff / $ceVolPrev) * 100, 2) : ($ceVolCurr > 0 ? 100 : 0);

            $ceOiCurr = $ceCurr ? (int) $ceCurr->open_interest : 0;
            $ceOiPrev = $cePrev ? (int) $cePrev->open_interest : 0;
            $ceOiDiff = $ceOiCurr - $ceOiPrev;
            $ceOiPct = ($ceOiPrev > 0) ? round(($ceOiDiff / $ceOiPrev) * 100, 2) : ($ceOiCurr > 0 ? 100 : 0);

            $ceBuildup = $this->classifyBuildup($cePriceDiff, $ceOiDiff);

            // PE metrics
            $peCloseCurr = $peCurr ? (float) $peCurr->close : 0;
            $peClosePrev = $pePrev ? (float) $pePrev->close : ($peCurr ? (float) $peCurr->open : 0);
            $pePriceDiff = round($peCloseCurr - $peClosePrev, 2);
            $pePricePct = ($peClosePrev > 0) ? round(($pePriceDiff / $peClosePrev) * 100, 2) : 0;

            $peVolCurr = $peCurr ? (int) $peCurr->volume : 0;
            $peVolPrev = $pePrev ? (int) $pePrev->volume : 0;
            $peVolDiff = $peVolCurr - $peVolPrev;
            $peVolPct = ($peVolPrev > 0) ? round(($peVolDiff / $peVolPrev) * 100, 2) : ($peVolCurr > 0 ? 100 : 0);

            $peOiCurr = $peCurr ? (int) $peCurr->open_interest : 0;
            $peOiPrev = $pePrev ? (int) $pePrev->open_interest : 0;
            $peOiDiff = $peOiCurr - $peOiPrev;
            $peOiPct = ($peOiPrev > 0) ? round(($peOiDiff / $peOiPrev) * 100, 2) : ($peOiCurr > 0 ? 100 : 0);

            $peBuildup = $this->classifyBuildup($pePriceDiff, $peOiDiff);

            // Accumulate totals
            $totalCeOiCurr += $ceOiCurr;
            $totalCeOiPrev += $ceOiPrev;
            $totalPeOiCurr += $peOiCurr;
            $totalPeOiPrev += $peOiPrev;

            $totalCeVolCurr += $ceVolCurr;
            $totalCeVolPrev += $ceVolPrev;
            $totalPeVolCurr += $peVolCurr;
            $totalPeVolPrev += $peVolPrev;

            if ($ceOiCurr > $maxCeOi) $maxCeOi = $ceOiCurr;
            if ($peOiCurr > $maxPeOi) $maxPeOi = $peOiCurr;
            if ($ceVolCurr > $maxCeVol) $maxCeVol = $ceVolCurr;
            if ($peVolCurr > $maxPeVol) $maxPeVol = $peVolCurr;

            // Moneyness Bucket Shifting
            $bucketKey = ($strike == $atmStrike) ? 'atm' : (($strike < $atmStrike) ? 'ce_itm_pe_otm' : 'ce_otm_pe_itm');
            if ($strike == $atmStrike) {
                $moneynessShifts['atm']['ce_oi_diff'] += $ceOiDiff;
                $moneynessShifts['atm']['pe_oi_diff'] += $peOiDiff;
                $moneynessShifts['atm']['ce_vol_diff'] += $ceVolDiff;
                $moneynessShifts['atm']['pe_vol_diff'] += $peVolDiff;
            } elseif ($strike > $atmStrike) {
                // CE is OTM, PE is ITM
                $moneynessShifts['otm']['ce_oi_diff'] += $ceOiDiff;
                $moneynessShifts['otm']['ce_vol_diff'] += $ceVolDiff;
                $moneynessShifts['itm']['pe_oi_diff'] += $peOiDiff;
                $moneynessShifts['itm']['pe_vol_diff'] += $peVolDiff;
            } else {
                // CE is ITM, PE is OTM
                $moneynessShifts['itm']['ce_oi_diff'] += $ceOiDiff;
                $moneynessShifts['itm']['ce_vol_diff'] += $ceVolDiff;
                $moneynessShifts['otm']['pe_oi_diff'] += $peOiDiff;
                $moneynessShifts['otm']['pe_vol_diff'] += $peVolDiff;
            }

            // Anomaly & Surge Detection
            $ceAlerts = $this->detectSurges($ceVolPct, $ceOiPct, $ceVolCurr, $ceOiDiff, $cePricePct, $ceMoneyness, 'CE', $strike);
            $peAlerts = $this->detectSurges($peVolPct, $peOiPct, $peVolCurr, $peOiDiff, $pePricePct, $peMoneyness, 'PE', $strike);

            foreach ($ceAlerts as $a) $anomaliesList[] = $a;
            foreach ($peAlerts as $a) $anomaliesList[] = $a;

            // Straddle & Bias
            $straddleClose = round($ceCloseCurr + $peCloseCurr, 2);
            $straddlePrevClose = round($ceClosePrev + $peClosePrev, 2);
            $straddleDiff = round($straddleClose - $straddlePrevClose, 2);
            $straddleDiffPct = ($straddlePrevClose > 0) ? round(($straddleDiff / $straddlePrevClose) * 100, 2) : 0;

            $strikeBias = 'Neutral';
            if ($ceOiDiff > 50000 && $ceOiDiff > ($peOiDiff * 1.4)) {
                $strikeBias = 'CE Writing (Resistance)';
            } elseif ($peOiDiff > 50000 && $peOiDiff > ($ceOiDiff * 1.4)) {
                $strikeBias = 'PE Writing (Support)';
            } elseif ($ceOiDiff > 50000 && $peOiDiff > 50000) {
                $strikeBias = 'Straddle Accumulation';
            } elseif ($ceOiDiff < -30000 && $peOiDiff < -30000) {
                $strikeBias = 'Dual Unwinding';
            }

            $rows[] = [
                'strike' => $strike,
                'distance_from_spot' => ($currSpot ? round($strike - $currSpot, 1) : 0),
                'is_atm' => ($strike == $atmStrike),
                'ce' => [
                    'moneyness' => $ceMoneyness,
                    'close' => $ceCloseCurr,
                    'prev_close' => $ceClosePrev,
                    'price_diff' => $cePriceDiff,
                    'price_pct' => $cePricePct,
                    'volume' => $ceVolCurr,
                    'prev_volume' => $ceVolPrev,
                    'volume_diff' => $ceVolDiff,
                    'volume_pct' => $ceVolPct,
                    'oi' => $ceOiCurr,
                    'prev_oi' => $ceOiPrev,
                    'oi_diff' => $ceOiDiff,
                    'oi_pct' => $ceOiPct,
                    'buildup' => $ceBuildup,
                    'alerts' => $ceAlerts,
                ],
                'pe' => [
                    'moneyness' => $peMoneyness,
                    'close' => $peCloseCurr,
                    'prev_close' => $peClosePrev,
                    'price_diff' => $pePriceDiff,
                    'price_pct' => $pePricePct,
                    'volume' => $peVolCurr,
                    'prev_volume' => $peVolPrev,
                    'volume_diff' => $peVolDiff,
                    'volume_pct' => $peVolPct,
                    'oi' => $peOiCurr,
                    'prev_oi' => $peOiPrev,
                    'oi_diff' => $peOiDiff,
                    'oi_pct' => $peOiPct,
                    'buildup' => $peBuildup,
                    'alerts' => $peAlerts,
                ],
                'straddle' => [
                    'close' => $straddleClose,
                    'prev_close' => $straddlePrevClose,
                    'diff' => $straddleDiff,
                    'diff_pct' => $straddleDiffPct,
                ],
                'bias' => $strikeBias,
            ];
        }

        // Apply Moneyness filter on rows if requested
        if ($moneynessFilter === 'atm') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['is_atm'] || abs($r['strike'] - $atmStrike) <= $step));
        } elseif ($moneynessFilter === 'otm') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['ce']['moneyness'] === 'OTM' || $r['pe']['moneyness'] === 'OTM'));
        } elseif ($moneynessFilter === 'itm') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['ce']['moneyness'] === 'ITM' || $r['pe']['moneyness'] === 'ITM'));
        } elseif ($moneynessFilter === 'surges') {
            $rows = array_values(array_filter($rows, fn ($r) => !empty($r['ce']['alerts']) || !empty($r['pe']['alerts'])));
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'strike_asc');
        usort($rows, function ($a, $b) use ($sortBy) {
            return match ($sortBy) {
                'strike_desc' => $b['strike'] <=> $a['strike'],
                'oi_diff_desc' => max($b['ce']['oi_diff'], $b['pe']['oi_diff']) <=> max($a['ce']['oi_diff'], $a['pe']['oi_diff']),
                'vol_diff_desc' => max($b['ce']['volume_diff'], $b['pe']['volume_diff']) <=> max($a['ce']['volume_diff'], $a['pe']['volume_diff']),
                'oi_desc' => ($b['ce']['oi'] + $b['pe']['oi']) <=> ($a['ce']['oi'] + $a['pe']['oi']),
                'vol_desc' => ($b['ce']['volume'] + $b['pe']['volume']) <=> ($a['ce']['volume'] + $a['pe']['volume']),
                default => $a['strike'] <=> $b['strike'],
            };
        });

        // 9. Macro Totals and PCR
        $ceOiDiffTotal = $totalCeOiCurr - $totalCeOiPrev;
        $ceOiPctTotal = ($totalCeOiPrev > 0) ? round(($ceOiDiffTotal / $totalCeOiPrev) * 100, 2) : 0;

        $peOiDiffTotal = $totalPeOiCurr - $totalPeOiPrev;
        $peOiPctTotal = ($totalPeOiPrev > 0) ? round(($peOiDiffTotal / $totalPeOiPrev) * 100, 2) : 0;

        $ceVolDiffTotal = $totalCeVolCurr - $totalCeVolPrev;
        $ceVolPctTotal = ($totalCeVolPrev > 0) ? round(($ceVolDiffTotal / $totalCeVolPrev) * 100, 2) : 0;

        $peVolDiffTotal = $totalPeVolCurr - $totalPeVolPrev;
        $peVolPctTotal = ($totalPeVolPrev > 0) ? round(($peVolDiffTotal / $totalPeVolPrev) * 100, 2) : 0;

        $pcrOiCurr = ($totalCeOiCurr > 0) ? round($totalPeOiCurr / $totalCeOiCurr, 3) : 0;
        $pcrOiPrev = ($totalCeOiPrev > 0) ? round($totalPeOiPrev / $totalCeOiPrev, 3) : 0;
        $pcrOiDiff = round($pcrOiCurr - $pcrOiPrev, 3);

        $pcrVolCurr = ($totalCeVolCurr > 0) ? round($totalPeVolCurr / $totalCeVolCurr, 3) : 0;
        $pcrVolPrev = ($totalCeVolPrev > 0) ? round($totalPeVolPrev / $totalCeVolPrev, 3) : 0;
        $pcrVolDiff = round($pcrVolCurr - $pcrVolPrev, 3);

        // Max Pain Strike across all strikes
        $maxPainStrike = $this->calculateMaxPain($rows, $step);

        // Major Resistance Wall (Highest CE OI) and Major Support Floor (Highest PE OI)
        $highestCeOiRow = collect($rows)->sortByDesc(fn ($r) => $r['ce']['oi'])->first();
        $highestPeOiRow = collect($rows)->sortByDesc(fn ($r) => $r['pe']['oi'])->first();
        $highestVolRow = collect($rows)->sortByDesc(fn ($r) => $r['ce']['volume'] + $r['pe']['volume'])->first();
        $biggestCeOiAddRow = collect($rows)->sortByDesc(fn ($r) => $r['ce']['oi_diff'])->first();
        $biggestPeOiAddRow = collect($rows)->sortByDesc(fn ($r) => $r['pe']['oi_diff'])->first();

        // 10. Institutional Hypothesis Engine
        $hypothesis = $this->synthesizeHypothesis(
            $moneynessShifts,
            $pcrOiCurr,
            $pcrOiDiff,
            $highestCeOiRow['strike'] ?? null,
            $highestPeOiRow['strike'] ?? null,
            $spotDiff,
            $nextDayOutcome
        );

        // 11. Multi-Day Timeline Progression of this Expiry (for line chart)
        $expiryTimeline = $this->buildExpiryTimeline($symbol, $selectedExpiry, $tradingDates);

        return [
            'ok' => true,
            'symbol' => $symbol,
            'symbols' => $symbols,
            'expiries' => $expiries,
            'selected_expiry' => $selectedExpiry,
            'selected_expiry_meta' => $currentExpiryMeta,
            'trading_dates' => $tradingDates,
            'selected_date' => $selectedDate,
            'selected_date_index' => $selectedDateIndex,
            'previous_date' => $previousDate,
            'next_date' => $nextDate,
            'is_first_day' => $isFirstDayOfExpiry,
            'spot' => [
                'current' => $currSpot,
                'previous' => $prevSpot,
                'diff' => $spotDiff,
                'diff_pct' => $spotDiffPct,
                'atm_strike' => $atmStrike,
            ],
            'next_day_outcome' => $nextDayOutcome,
            'macro' => [
                'ce_oi_curr' => $totalCeOiCurr,
                'ce_oi_prev' => $totalCeOiPrev,
                'ce_oi_diff' => $ceOiDiffTotal,
                'ce_oi_pct' => $ceOiPctTotal,

                'pe_oi_curr' => $totalPeOiCurr,
                'pe_oi_prev' => $totalPeOiPrev,
                'pe_oi_diff' => $peOiDiffTotal,
                'pe_oi_pct' => $peOiPctTotal,

                'ce_vol_curr' => $totalCeVolCurr,
                'ce_vol_prev' => $totalCeVolPrev,
                'ce_vol_diff' => $ceVolDiffTotal,
                'ce_vol_pct' => $ceVolPctTotal,

                'pe_vol_curr' => $totalPeVolCurr,
                'pe_vol_prev' => $totalPeVolPrev,
                'pe_vol_diff' => $peVolDiffTotal,
                'pe_vol_pct' => $peVolPctTotal,

                'pcr_oi' => $pcrOiCurr,
                'pcr_oi_prev' => $pcrOiPrev,
                'pcr_oi_diff' => $pcrOiDiff,

                'pcr_vol' => $pcrVolCurr,
                'pcr_vol_prev' => $pcrVolPrev,
                'pcr_vol_diff' => $pcrVolDiff,

                'max_pain' => $maxPainStrike,
                'highest_ce_oi_strike' => $highestCeOiRow['strike'] ?? null,
                'highest_ce_oi_val' => $highestCeOiRow['ce']['oi'] ?? 0,
                'highest_pe_oi_strike' => $highestPeOiRow['strike'] ?? null,
                'highest_pe_oi_val' => $highestPeOiRow['pe']['oi'] ?? 0,
                'highest_vol_strike' => $highestVolRow['strike'] ?? null,
                'biggest_ce_oi_add' => [
                    'strike' => $biggestCeOiAddRow['strike'] ?? null,
                    'diff' => $biggestCeOiAddRow['ce']['oi_diff'] ?? 0,
                    'pct' => $biggestCeOiAddRow['ce']['oi_pct'] ?? 0,
                ],
                'biggest_pe_oi_add' => [
                    'strike' => $biggestPeOiAddRow['strike'] ?? null,
                    'diff' => $biggestPeOiAddRow['pe']['oi_diff'] ?? 0,
                    'pct' => $biggestPeOiAddRow['pe']['oi_pct'] ?? 0,
                ],
            ],
            'moneyness_shifts' => $moneynessShifts,
            'hypothesis' => $hypothesis,
            'anomalies' => array_slice($anomaliesList, 0, 12),
            'max_depths' => [
                'ce_oi' => $maxCeOi,
                'pe_oi' => $maxPeOi,
                'ce_vol' => $maxCeVol,
                'pe_vol' => $maxPeVol,
            ],
            'rows' => $rows,
            'filters' => [
                'strike_range' => $strikeRangeFilter,
                'moneyness' => $moneynessFilter,
                'sort_by' => $sortBy,
                'min_strike' => $customMinStrike,
                'max_strike' => $customMaxStrike,
            ],
            'timeline' => $expiryTimeline,
            'multiday_strikes' => $this->buildMultiDayStrikes($symbol, $selectedExpiry, $tradingDates, $filteredStrikes, $atmStrike, $step),
            'trading_days' => array_map(function ($date, $idx) {
                return [
                    'day_number' => $idx + 1,
                    'quote_date' => $date,
                    'formatted' => Carbon::parse($date)->format('d M'),
                ];
            }, $tradingDates, array_keys($tradingDates)),
        ];
    }

    /**
     * Build multi-day progression across available days for active strikes (±10 strikes around ATM).
     */
    private function buildMultiDayStrikes(
        string $symbol,
        string $expiry,
        array $tradingDates,
        $filteredStrikes,
        int $atmStrike,
        int $step
    ): array {
        if (empty($tradingDates) || $filteredStrikes->isEmpty()) {
            return [];
        }

        $minStrike = $filteredStrikes->min();
        $maxStrike = $filteredStrikes->max();

        $quotes = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $expiry)
            ->whereIn('quote_date', $tradingDates)
            ->whereBetween('strike', [$minStrike, $maxStrike])
            ->select('quote_date', 'strike', 'option_type', 'close', 'volume', 'open_interest')
            ->get();

        $grouped = [];
        foreach ($quotes as $q) {
            $grouped[(int)$q->strike][$q->quote_date][strtoupper($q->option_type)] = $q;
        }

        $multiDayStrikes = [];

        foreach ($filteredStrikes as $strike) {
            $strike = (int)$strike;
            $days = [];
            $prevStraddle = null;
            $prevVol = null;
            $prevOi = null;

            $firstStraddle = null;
            $latestStraddle = null;
            $firstOi = null;
            $latestOi = null;

            foreach ($tradingDates as $idx => $date) {
                $ce = $grouped[$strike][$date]['CE'] ?? null;
                $pe = $grouped[$strike][$date]['PE'] ?? null;

                $ceClose = ($ce && $ce->close > 0) ? (float)$ce->close : null;
                $peClose = ($pe && $pe->close > 0) ? (float)$pe->close : null;

                // A true Straddle requires BOTH legs (CE + PE) to be actively traded (> 0)
                $hasBothLegs = ($ceClose !== null && $peClose !== null);
                $straddle = $hasBothLegs ? round($ceClose + $peClose, 2) : null;

                $ceVol = $ce ? (int)$ce->volume : 0;
                $peVol = $pe ? (int)$pe->volume : 0;
                $vol = $ceVol + $peVol;

                $ceOi = $ce ? (int)$ce->open_interest : 0;
                $peOi = $pe ? (int)$pe->open_interest : 0;
                $oi = $ceOi + $peOi;

                if ($firstStraddle === null && $straddle !== null) $firstStraddle = $straddle;
                if ($straddle !== null) $latestStraddle = $straddle;
                if ($firstOi === null && $oi > 0) $firstOi = $oi;
                if ($oi > 0) $latestOi = $oi;

                $straddleDiff = ($prevStraddle !== null && $straddle !== null) ? round($straddle - $prevStraddle, 2) : null;
                $straddlePct = ($prevStraddle !== null && $straddle !== null && $prevStraddle > 0) ? round(($straddleDiff / $prevStraddle) * 100, 1) : null;

                $volDiff = ($prevVol !== null) ? ($vol - $prevVol) : 0;
                $volPct = ($prevVol !== null && $prevVol > 0) ? round(($volDiff / $prevVol) * 100, 1) : 0;

                $oiDiff = ($prevOi !== null) ? ($oi - $prevOi) : 0;
                $oiPct = ($prevOi !== null && $prevOi > 0) ? round(($oiDiff / $prevOi) * 100, 1) : 0;

                $days[] = [
                    'day_number' => $idx + 1,
                    'quote_date' => $date,
                    'formatted_date' => Carbon::parse($date)->format('d M'),
                    'straddle' => $straddle,
                    'straddle_diff' => $straddleDiff,
                    'straddle_pct' => $straddlePct,
                    'ce_close' => $ceClose,
                    'pe_close' => $peClose,
                    'vol' => $vol,
                    'vol_diff' => $volDiff,
                    'vol_pct' => $volPct,
                    'oi' => $oi,
                    'oi_diff' => $oiDiff,
                    'oi_pct' => $oiPct,
                ];

                if ($straddle !== null) {
                    $prevStraddle = $straddle;
                }
                $prevVol = $vol;
                $prevOi = $oi;
            }

            $netStraddleDiff = ($firstStraddle !== null && $latestStraddle !== null) ? round($latestStraddle - $firstStraddle, 2) : null;
            $netStraddlePct = ($firstStraddle !== null && $firstStraddle > 0 && $netStraddleDiff !== null) ? round(($netStraddleDiff / $firstStraddle) * 100, 1) : null;

            $firstOiVal = $firstOi ?? 0;
            $latestOiVal = $latestOi ?? 0;
            $netOiDiff = $latestOiVal - $firstOiVal;
            $netOiPct = ($firstOiVal > 0) ? round(($netOiDiff / $firstOiVal) * 100, 1) : 0;

            $multiDayStrikes[] = [
                'strike' => $strike,
                'is_atm' => ($strike === $atmStrike),
                'diff_atm' => $strike - $atmStrike,
                'moneyness' => ($strike < $atmStrike) ? 'CE ITM / PE OTM' : (($strike === $atmStrike) ? 'ATM' : 'CE OTM / PE ITM'),
                'days' => $days,
                'net_straddle_diff' => $netStraddleDiff,
                'net_straddle_pct' => $netStraddlePct,
                'net_oi_diff' => $netOiDiff,
                'net_oi_pct' => $netOiPct,
            ];
        }

        return $multiDayStrikes;
    }

    /**
     * Standard Quantitative Option Buildup Classification.
     */
    private function classifyBuildup(float $priceDiff, int $oiDiff): array
    {
        if ($oiDiff > 0 && $priceDiff > 0) {
            return ['type' => 'Long Buildup', 'color' => 'emerald', 'code' => 'LB', 'desc' => 'Bullish Buying (Price ↑ & OI ↑)'];
        } elseif ($oiDiff > 0 && $priceDiff < 0) {
            return ['type' => 'Short Buildup', 'color' => 'rose', 'code' => 'SB', 'desc' => 'Bearish Writing/Shorts (Price ↓ & OI ↑)'];
        } elseif ($oiDiff < 0 && $priceDiff < 0) {
            return ['type' => 'Long Unwinding', 'color' => 'amber', 'code' => 'LU', 'desc' => 'Buyers Closing (Price ↓ & OI ↓)'];
        } elseif ($oiDiff < 0 && $priceDiff > 0) {
            return ['type' => 'Short Covering', 'color' => 'sky', 'code' => 'SC', 'desc' => 'Short Squeeze/Covering (Price ↑ & OI ↓)'];
        }

        return ['type' => 'Neutral', 'color' => 'slate', 'code' => '-', 'desc' => 'Unchanged'];
    }

    /**
     * Detect Volume & OI Surges / Anomaly Discrepancies.
     */
    private function detectSurges(
        float $volPct,
        float $oiPct,
        int $volCurr,
        int $oiDiff,
        float $pricePct,
        string $moneyness,
        string $type,
        int $strike
    ): array {
        $alerts = [];

        // 1. Extreme Volume Spike (>100% and substantial volume)
        if ($volPct >= 100 && $volCurr >= 100000) {
            $alerts[] = [
                'type' => 'volume_spike',
                'severity' => $volPct >= 200 ? 'high' : 'medium',
                'badge' => '⚡ Vol Spike +' . round($volPct) . '%',
                'strike' => $strike,
                'option_type' => $type,
                'message' => "{$strike} {$type} volume surged by " . round($volPct) . "% to " . number_format($volCurr),
            ];
        }

        // 2. High OI Addition Surge (>40% increase with >50k contracts)
        if ($oiPct >= 40 && $oiDiff >= 50000) {
            $alerts[] = [
                'type' => 'oi_surge',
                'severity' => 'high',
                'badge' => '🔥 OI Blast +' . round($oiPct) . '%',
                'strike' => $strike,
                'option_type' => $type,
                'message' => "{$strike} {$type} added " . number_format($oiDiff) . " OI contracts (+" . round($oiPct) . "%)",
            ];
        }

        // 3. Aggressive Institutional Writing in OTM
        if ($moneyness === 'OTM' && $oiDiff >= 50000 && $pricePct <= -10) {
            $alerts[] = [
                'type' => 'otm_writing',
                'severity' => 'critical',
                'badge' => '🛡️ Heavy OTM Writing',
                'strike' => $strike,
                'option_type' => $type,
                'message' => "Aggressive institutional writing at OTM {$strike} {$type}: price dropped {$pricePct}% as sellers injected " . number_format($oiDiff) . " OI",
            ];
        }

        // 4. Short Covering Squeeze
        if ($pricePct >= 25 && $oiDiff <= -20000) {
            $alerts[] = [
                'type' => 'short_squeeze',
                'severity' => 'high',
                'badge' => '🚀 Short Squeeze',
                'strike' => $strike,
                'option_type' => $type,
                'message' => "Short squeeze on {$strike} {$type}: price jumped +{$pricePct}% as shorts unwound " . number_format(abs($oiDiff)) . " OI",
            ];
        }

        return $alerts;
    }

    /**
     * Synthesize Institutional Hypothesis and Next-Day Alignment.
     */
    private function synthesizeHypothesis(
        array $moneynessShifts,
        float $pcrOi,
        float $pcrDiff,
        ?int $resStrike,
        ?int $supStrike,
        float $spotDiff,
        ?array $nextDayOutcome
    ): array {
        $otmCeDiff = $moneynessShifts['otm']['ce_oi_diff'];
        $otmPeDiff = $moneynessShifts['otm']['pe_oi_diff'];

        $bias = 'NEUTRAL / BALANCED';
        $color = 'slate';
        $summary = '';
        $thesis = '';

        if ($otmCeDiff > ($otmPeDiff * 1.5) && $otmCeDiff > 100000) {
            $bias = 'BEARISH / CAPPED UPSIDE (HEAVY CALL WRITING)';
            $color = 'rose';
            $summary = "Institutions aggressively added Out-Of-The-Money Call OI (+".number_format($otmCeDiff)." contracts), far outpacing Put addition (+".number_format($otmPeDiff).").";
            $thesis = "Major institutional writers built an aggressive ceiling/resistance wall around {$resStrike}. The market was capped from upside rallies, signaling defensive positioning or downside bias.";
        } elseif ($otmPeDiff > ($otmCeDiff * 1.5) && $otmPeDiff > 100000) {
            $bias = 'BULLISH / STRONG DOWNSIDE FLOOR (PUT WRITING DOMINANT)';
            $color = 'emerald';
            $summary = "Institutions aggressively wrote Out-Of-The-Money Puts (+".number_format($otmPeDiff)." contracts), compared to Call addition (+".number_format($otmCeDiff).").";
            $thesis = "Smart money is pocketing put premiums, creating a strong institutional floor around {$supStrike}. Expect market support and upward drift.";
        } elseif ($otmCeDiff > 100000 && $otmPeDiff > 100000) {
            $bias = 'RANGEBOUND / STRANGLE CONFINEMENT';
            $color = 'amber';
            $summary = "Both OTM Calls (+".number_format($otmCeDiff).") and OTM Puts (+".number_format($otmPeDiff).") experienced heavy simultaneous additions.";
            $thesis = "Institutions are executing short strangles / rangebound hedging, locking the market in a corridor between Support {$supStrike} and Resistance {$resStrike}.";
        } else {
            $bias = 'CONSOLIDATION / MIXED ACTIVITY';
            $color = 'indigo';
            $summary = "Mild shifts across strikes with no singular massive OTM skew.";
            $thesis = "PCR is at {$pcrOi} (Δ {$pcrDiff}). Market is tracking spot momentum without extreme institutional directional skew.";
        }

        // Backtesting Verification vs Next Day
        $backtestVerdict = null;
        if ($nextDayOutcome) {
            $nextPts = $nextDayOutcome['change_pts'];
            $isBearishPrediction = str_contains($bias, 'BEARISH');
            $isBullishPrediction = str_contains($bias, 'BULLISH');
            $isRangePrediction = str_contains($bias, 'RANGEBOUND');

            if ($isBearishPrediction && $nextPts < 0) {
                $backtestVerdict = [
                    'status' => 'SUCCESS',
                    'badge' => '✅ Hypothesis Validated',
                    'color' => 'emerald',
                    'message' => "Heavy OTM Call writing predicted downward drift, and NIFTY indeed dropped by {$nextPts} pts next day!",
                ];
            } elseif ($isBullishPrediction && $nextPts > 0) {
                $backtestVerdict = [
                    'status' => 'SUCCESS',
                    'badge' => '✅ Hypothesis Validated',
                    'color' => 'emerald',
                    'message' => "Aggressive OTM Put writing indicated a solid floor, and NIFTY indeed rallied by +{$nextPts} pts next day!",
                ];
            } elseif ($isRangePrediction && abs($nextPts) <= 80) {
                $backtestVerdict = [
                    'status' => 'SUCCESS',
                    'badge' => '✅ Hypothesis Validated',
                    'color' => 'emerald',
                    'message' => "Dual-side writing predicted rangebound action, and NIFTY remained confined (only {$nextPts} pts change)!",
                ];
            } elseif ($isBearishPrediction && $nextPts > 50) {
                $backtestVerdict = [
                    'status' => 'INVERTED',
                    'badge' => '⚡ Short Squeeze / Inverted',
                    'color' => 'rose',
                    'message' => "Heavy Call writing was tested by an unexpected market rally of +{$nextPts} pts, likely squeezing call writers into covering!",
                ];
            } elseif ($isBullishPrediction && $nextPts < -50) {
                $backtestVerdict = [
                    'status' => 'INVERTED',
                    'badge' => '⚠️ Floor Breached',
                    'color' => 'rose',
                    'message' => "Put writers were challenged as market experienced a sharp drop of {$nextPts} pts next day.",
                ];
            } else {
                $backtestVerdict = [
                    'status' => 'MODERATE',
                    'badge' => '⚖️ Neutral / Flat Reaction',
                    'color' => 'slate',
                    'message' => "Next day move was muted ({$nextPts} pts / {$nextDayOutcome['change_pct']}%), aligning with consolidation.",
                ];
            }
        }

        return [
            'bias' => $bias,
            'color' => $color,
            'summary' => $summary,
            'thesis' => $thesis,
            'otm_ce_add' => $otmCeDiff,
            'otm_pe_add' => $otmPeDiff,
            'verdict' => $backtestVerdict,
        ];
    }

    /**
     * Calculate Max Pain Strike for current option state.
     */
    private function calculateMaxPain(array $rows, int $step): ?int
    {
        if (empty($rows)) return null;

        $minStrike = min(array_column($rows, 'strike'));
        $maxStrike = max(array_column($rows, 'strike'));

        $lowestLoss = PHP_FLOAT_MAX;
        $maxPainStrike = null;

        for ($expPrice = $minStrike; $expPrice <= $maxStrike; $expPrice += $step) {
            $totalLoss = 0;
            foreach ($rows as $r) {
                $k = $r['strike'];
                // CE loss if expired above strike
                if ($expPrice > $k) {
                    $totalLoss += ($expPrice - $k) * $r['ce']['oi'];
                }
                // PE loss if expired below strike
                if ($expPrice < $k) {
                    $totalLoss += ($k - $expPrice) * $r['pe']['oi'];
                }
            }

            if ($totalLoss < $lowestLoss) {
                $lowestLoss = $totalLoss;
                $maxPainStrike = $expPrice;
            }
        }

        return $maxPainStrike;
    }

    /**
     * Build multi-day progression summary for this expiry.
     */
    private function buildExpiryTimeline(string $symbol, string $expiry, array $tradingDates): array
    {
        $timeline = [];

        $allDayQuotes = DB::table('daily_ohlc_quotes')
            ->where('symbol_name', $symbol)
            ->where('expiry_date', $expiry)
            ->select(
                'quote_date',
                'option_type',
                DB::raw('SUM(volume) as total_volume'),
                DB::raw('SUM(open_interest) as total_oi')
            )
            ->groupBy('quote_date', 'option_type')
            ->get();

        $quotesGrouped = [];
        foreach ($allDayQuotes as $q) {
            $quotesGrouped[$q->quote_date][strtoupper($q->option_type)] = $q;
        }

        $spots = DB::table('daily_trend')
            ->where('symbol_name', $symbol)
            ->whereIn('trading_date', $tradingDates)
            ->get()
            ->keyBy('trading_date');

        foreach ($tradingDates as $idx => $date) {
            $ceData = $quotesGrouped[$date]['CE'] ?? null;
            $peData = $quotesGrouped[$date]['PE'] ?? null;
            $spotRow = $spots->get($date);

            $ceOi = $ceData ? (int) $ceData->total_oi : 0;
            $peOi = $peData ? (int) $peData->total_oi : 0;
            $ceVol = $ceData ? (int) $ceData->total_volume : 0;
            $peVol = $peData ? (int) $peData->total_volume : 0;
            $pcr = ($ceOi > 0) ? round($peOi / $ceOi, 3) : 0;

            $timeline[] = [
                'day_number' => $idx + 1,
                'quote_date' => $date,
                'formatted_date' => Carbon::parse($date)->format('d M'),
                'spot_close' => $spotRow && $spotRow->index_close ? (float) $spotRow->index_close : null,
                'ce_oi' => $ceOi,
                'pe_oi' => $peOi,
                'total_oi' => $ceOi + $peOi,
                'ce_volume' => $ceVol,
                'pe_volume' => $peVol,
                'total_volume' => $ceVol + $peVol,
                'pcr_oi' => $pcr,
            ];
        }

        return $timeline;
    }

    /**
     * Fallback empty response structure.
     */
    private function emptyResponse(string $message, string $symbol, array $symbols, $expiries = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'symbol' => $symbol,
            'symbols' => $symbols,
            'expiries' => $expiries instanceof \Illuminate\Support\Collection ? $expiries : collect($expiries),
            'selected_expiry' => null,
            'selected_expiry_meta' => null,
            'trading_dates' => [],
            'selected_date' => null,
            'selected_date_index' => 0,
            'previous_date' => null,
            'next_date' => null,
            'is_first_day' => false,
            'spot' => ['current' => null, 'previous' => null, 'diff' => 0, 'diff_pct' => 0, 'atm_strike' => 24500],
            'next_day_outcome' => null,
            'macro' => [
                'ce_oi_curr' => 0, 'ce_oi_prev' => 0, 'ce_oi_diff' => 0, 'ce_oi_pct' => 0,
                'pe_oi_curr' => 0, 'pe_oi_prev' => 0, 'pe_oi_diff' => 0, 'pe_oi_pct' => 0,
                'ce_vol_curr' => 0, 'ce_vol_prev' => 0, 'ce_vol_diff' => 0, 'ce_vol_pct' => 0,
                'pe_vol_curr' => 0, 'pe_vol_prev' => 0, 'pe_vol_diff' => 0, 'pe_vol_pct' => 0,
                'pcr_oi' => 0, 'pcr_oi_prev' => 0, 'pcr_oi_diff' => 0,
                'pcr_vol' => 0, 'pcr_vol_prev' => 0, 'pcr_vol_diff' => 0,
                'max_pain' => null, 'highest_ce_oi_strike' => null, 'highest_pe_oi_strike' => null,
            ],
            'moneyness_shifts' => [
                'atm' => ['ce_oi_diff' => 0, 'pe_oi_diff' => 0, 'ce_vol_diff' => 0, 'pe_vol_diff' => 0],
                'otm' => ['ce_oi_diff' => 0, 'pe_oi_diff' => 0, 'ce_vol_diff' => 0, 'pe_vol_diff' => 0],
                'itm' => ['ce_oi_diff' => 0, 'pe_oi_diff' => 0, 'ce_vol_diff' => 0, 'pe_vol_diff' => 0],
            ],
            'hypothesis' => ['bias' => 'NO DATA', 'color' => 'slate', 'summary' => $message, 'thesis' => '', 'verdict' => null],
            'anomalies' => [],
            'max_depths' => ['ce_oi' => 1, 'pe_oi' => 1, 'ce_vol' => 1, 'pe_vol' => 1],
            'rows' => [],
            'filters' => ['strike_range' => '10', 'moneyness' => 'all', 'sort_by' => 'strike_asc', 'min_strike' => null, 'max_strike' => null],
            'timeline' => [],
            'multiday_strikes' => [],
            'trading_days' => [],
        ];
    }
}
