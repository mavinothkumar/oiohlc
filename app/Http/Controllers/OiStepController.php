<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OiStepController extends Controller
{
    // ── Configurable interval ──────────────────────────────────────────────
    const INTERVAL = '5minute';   // Change to '3minute' whenever needed
    const SLOT_MINUTES = 5;       // Must match the interval above

    // Market hours: 09:15 → 15:30
    const MARKET_START = '09:15';
    const MARKET_END   = '15:30';

    /**
     * Initial page load — shows the filter form (no table yet).
     */
    public function index(Request $request)
    {
        $request->validate([
            'date'    => 'nullable|date',
            'expiry'  => 'nullable|date',
            'strikes' => 'nullable|array|max:6',
            'strikes.*' => 'nullable|numeric',
        ]);

        $date    = $request->input('date');
        $expiry  = $request->input('expiry');
        $strikes = array_filter(
            (array) $request->input('strikes', []),
            fn($v) => $v !== null && $v !== ''
        );
        $strikes = array_map('floatval', array_values($strikes));

        sort($strikes);

        // Build ordered slot list for the JS to reference
        $slots = $this->buildSlots();

        return view('test.oi-step', [
            'date'       => $date,
            'expiry'     => $expiry,
            'strikes'    => $strikes,
            'slots'      => $slots,
            'interval'   => self::INTERVAL,
            'tableData'  => [],
            'timestamps' => [],
            'allStrikes' => $strikes,
            'highlight'  => ['oi_pos' => [], 'oi_neg' => [], 'vol_pos' => [], 'vol_neg' => []],
        ]);
    }

    /**
     * AJAX — returns data for a single time slot (by slot index).
     * The front-end appends (Next) or removes last (Previous) rows.
     */
    public function fetchSlot(Request $request)
    {
        $request->validate([
            'date'       => 'required|date',
            'expiry'     => 'required|date',
            'strikes'    => 'required|array',
            'strikes.*'  => 'required|numeric',
            'slot_index' => 'required|integer|min:0',
        ]);

        $date    = $request->input('date');
        $expiry  = $request->input('expiry');
        $strikes = array_map('floatval', $request->input('strikes'));
        $slotIdx = (int) $request->input('slot_index');
        sort($strikes);

        $slots = $this->buildSlots();

        if (! isset($slots[$slotIdx])) {
            return response()->json(['error' => 'Invalid slot index'], 422);
        }

        $currSlot = $slots[$slotIdx];                          // e.g. "09:30"
        $prevSlot = $slotIdx > 0 ? $slots[$slotIdx - 1] : null; // e.g. "09:25"

        // ── Fetch BOTH current and previous slot rows in one query ────────────
        $timesToFetch = array_filter([$currSlot, $prevSlot]); // ["09:30", "09:25"]

        $rows = DB::table('expired_ohlc')
                  ->where('underlying_symbol', 'NIFTY')
                  ->where('expiry', $expiry)
                  ->where('interval', self::INTERVAL)
                  ->whereDate('timestamp', $date)
                  ->whereIn('strike', $strikes)
                  ->whereIn('instrument_type', ['CE', 'PE'])
                  ->where(function ($q) use ($timesToFetch) {
                      foreach ($timesToFetch as $time) {
                          // Matches "HH:MM" against the timestamp column safely
                          $q->orWhereRaw("TIME(timestamp) = ?", [$time . ':00']);
                      }
                  })
                  ->get(['strike', 'instrument_type', 'close', 'volume', 'open_interest', 'timestamp']);

        // ── Group by [strike][type][H:i] ──────────────────────────────────────
        $grouped = [];
        foreach ($rows as $row) {
            $timeKey = \Carbon\Carbon::parse($row->timestamp)->format('H:i'); // "09:30"
            $grouped[(float) $row->strike][$row->instrument_type][$timeKey] = $row;
        }

        // ── Build response data ───────────────────────────────────────────────
        $rowData = [];

        foreach ($strikes as $strike) {
            foreach (['CE', 'PE'] as $type) {
                $curr = $grouped[$strike][$type][$currSlot] ?? null;
                $prev = $prevSlot ? ($grouped[$strike][$type][$prevSlot] ?? null) : null;

                $oiDiff    = ($curr && $prev) ? (int)$curr->open_interest - (int)$prev->open_interest : null;
                $volDiff   = ($curr && $prev) ? (int)$curr->volume        - (int)$prev->volume        : null;
                $closeDiff = ($curr && $prev) ? round((float)$curr->close - (float)$prev->close, 2)   : null;

                $buildUp = null;
                if ($oiDiff !== null && $closeDiff !== null) {
                    if      ($oiDiff > 0 && $closeDiff > 0) $buildUp = 'LB';
                    elseif  ($oiDiff > 0 && $closeDiff < 0) $buildUp = 'SB';
                    elseif  ($oiDiff < 0 && $closeDiff < 0) $buildUp = 'LU';
                    elseif  ($oiDiff < 0 && $closeDiff > 0) $buildUp = 'SC';
                }

                $rowData[$strike][$type] = [
                    'close'      => $curr ? (float)$curr->close          : null,
                    'close_diff' => $closeDiff,
                    'oi'         => $curr ? (int)$curr->open_interest     : null,
                    'oi_diff'    => $oiDiff,
                    'vol'        => $curr ? (int)$curr->volume            : null,
                    'vol_diff'   => $volDiff,
                    'build_up'   => $buildUp,
                ];
            }
        }

        return response()->json([
            'slot_index'  => $slotIdx,
            'label'       => $currSlot,
            'total_slots' => count($slots),
            'strikes'     => $strikes,
            'data'        => $rowData,
        ]);
    }


    /**
     * AJAX — fetch expiries for a date (reused from OiDiffController).
     */
    public function fetchExpiries(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $expiry = DB::table('expired_expiries')
                    ->where('underlying_symbol', 'NIFTY')
                    ->where('instrument_type', 'OPT')
                    ->where('expiry_date', '>=', $request->date)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

        $strikes   = collect();
        $atmStrike = null;
        $prevDay = '';
        if ($expiry) {

            $prevDay = DB::table('nse_working_days')
                         ->where('working_date', '<', $request->date)
                         ->orderBy('working_date', 'desc')
                         ->value('working_date');

            $atmStrike = DB::table('daily_trend')
                           ->where('symbol_name', 'NIFTY')
                           ->where('quote_date', $request->date)
                           ->value('atm_index_open');

            $atmIndexOpen = (float) $atmStrike;
            $step         = 50;

            $strikes = [
                $atmIndexOpen - 3 * $step,
                $atmIndexOpen - 2 * $step,
                $atmIndexOpen - 1 * $step,
                $atmIndexOpen,
                $atmIndexOpen + 1 * $step,
                $atmIndexOpen + 2 * $step,
                $atmIndexOpen + 3 * $step,
            ];
        }

        return response()->json([
            'prevDay'  => $prevDay,
            'expiry'  => $expiry,
            'strikes' => $strikes,
            'atm'     => $atmStrike ? (int)$atmStrike : null,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Build an ordered array of slot time labels: ["09:15", "09:20", ...]
     */
    private function buildSlots(): array
    {
        $slots  = [];
        $start  = strtotime(self::MARKET_START);
        $end    = strtotime(self::MARKET_END);
        $step   = self::SLOT_MINUTES * 60;

        for ($t = $start; $t <= $end; $t += $step) {
            $slots[] = date('H:i', $t);
        }

        return $slots;
    }

    /**
     * Convert "09:15" slot label to a full timestamp string for DB compare.
     */
    private function slotToTimestamp(string $timeLabel): string
    {
        return $timeLabel; // used for TIME() comparison; date is applied via whereDate
    }

    /**
     * Find a DB timestamp string matching a given H:i slot label.
     */
    private function findMatchingTs(array $timestamps, string $slotLabel): ?string
    {
        foreach ($timestamps as $ts) {
            if (date('H:i', strtotime($ts)) === $slotLabel) {
                return $ts;
            }
        }
        return null;
    }
}
