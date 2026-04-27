<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OiBuildupController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'underlying_symbol' => 'nullable|string',
            'expiry'            => 'nullable|date',
            'instrument_type'   => 'nullable|string',
            'at'                => 'nullable|date_format:Y-m-d\TH:i',
            'limit'             => 'nullable|integer|min:1|max:100',
        ]);

        $datasets = [];

        $hasFilters = filled($validated['at'] ?? null) || filled($validated['expiry'] ?? null);

        if ( ! $hasFilters) {
            return view('oi-buildup.index', [
                'no_filter' => true,
                'filters'   => [
                    'underlying_symbol' => '',
                    'expiry'            => '',
                    'instrument_type'   => '',
                    'interval'          => 5,
                    'at'                => now()->format('Y-m-d') . 'T09:15',
                    'limit'             => 6,
                ],
                'datasets'  => $datasets,
            ]);
        }

        $limit = (int) ($validated['limit'] ?? 6);
        $at    = ! empty($validated['at']) ? $validated['at'] : null;
        $at    = $at
            ? Carbon::createFromFormat('Y-m-d\TH:i', $at)
            : Carbon::now();

        // Snap to nearest 5-minute boundary
        $minutes          = floor($at->minute / 5) * 5;
        $atDateTime       = $at->copy()->setTime($at->hour, $minutes, 0);
        $atDateTimeString = $atDateTime->format('Y-m-d H:i:s');
        $dayOpenString    = $atDateTime->format('Y-m-d') . ' 09:15:00';

        // Build shared where conditions
        $baseWhere = [];
        if ( ! empty($validated['underlying_symbol'])) {
            $baseWhere[] = ['underlying_symbol', '=', $validated['underlying_symbol']];
        }
        if ( ! empty($validated['expiry'])) {
            $baseWhere[] = ['expiry', '=', $validated['expiry']];
        }
        if ( ! empty($validated['instrument_type'])) {
            $baseWhere[] = ['instrument_type', '=', $validated['instrument_type']];
        }

        // Intervals and their lookback in minutes.
        // 375 = full day (compare vs 09:15 open candle).
        $intervals = [5, 10, 15, 30, 60, 375];

        // Collect all unique "from" timestamps we need across all intervals,
        // so we can fetch EVERYTHING in just TWO queries total:
        //   Query A — current candle   (timestamp = $atDateTime)          — all instruments
        //   Query B — reference candles (timestamp IN [...from timestamps]) — all instruments
        $fromTimestamps = [];
        foreach ($intervals as $intervalMinutes) {
            if ($intervalMinutes === 375) {
                $fromTimestamps[] = $dayOpenString;
            } else {
                $fromTimestamps[] = $atDateTime->copy()->subMinutes($intervalMinutes)->format('Y-m-d H:i:s');
            }
        }
        $fromTimestamps = array_unique($fromTimestamps);

        // ── Query A: current candle ──────────────────────────────────────────
        $currentRows = DB::table('expired_ohlc')
                         ->where($baseWhere)
                         ->where('strike', '>', 0)
                         ->where('interval', '5minute')
                         ->where('timestamp', $atDateTimeString)
                         ->get()
                         ->keyBy('instrument_key');

        if ($currentRows->isEmpty()) {
            // No data at all for this timestamp — return empty datasets
            foreach ($intervals as $i) {
                $datasets[$i] = [];
            }

            return view('oi-buildup.index', [
                'filters'     => [
                    'underlying_symbol' => $validated['underlying_symbol'] ?? '',
                    'expiry'            => $validated['expiry'] ?? '',
                    'instrument_type'   => $validated['instrument_type'] ?? '',
                    'interval'          => $validated['interval'] ?? '',
                    'at'                => $at,
                    'limit'             => $limit,
                ],
                'datasets'    => $datasets,
                'oiThreshold' => $this->amountForToday(),
            ]);
        }

        $instrumentKeys = $currentRows->keys()->all();

        // ── Query B: all reference candles in one shot ───────────────────────
        $referenceRows = DB::table('expired_ohlc')
                           ->where($baseWhere)
                           ->where('interval', '5minute')
                           ->whereIn('instrument_key', $instrumentKeys)
                           ->whereIn('timestamp', $fromTimestamps)
                           ->get()
                           ->groupBy('instrument_key');  // instrument_key → Collection of candles at various timestamps

        // ── Build datasets per interval ──────────────────────────────────────
        foreach ($intervals as $intervalMinutes) {
            $fromString = $intervalMinutes === 375
                ? $dayOpenString
                : $atDateTime->copy()->subMinutes($intervalMinutes)->format('Y-m-d H:i:s');

            $rows = [];

            foreach ($currentRows as $ik => $current) {
                // Find the matching reference candle for this interval
                $prev = ($referenceRows[$ik] ?? collect())
                    ->firstWhere('timestamp', $fromString);

                if ( ! $prev) {
                    continue;
                }

                $deltaOi    = (int)   $current->open_interest - (int)   $prev->open_interest;
                $deltaClose = (float) $current->close         - (float) $prev->close;

                if ($deltaOi === 0 || $deltaClose === 0) {
                    $buildup = 'Neutral';
                } elseif ($deltaClose > 0 && $deltaOi > 0) {
                    $buildup = 'Long';
                } elseif ($deltaClose < 0 && $deltaOi > 0) {
                    $buildup = 'Short';
                } elseif ($deltaClose > 0 && $deltaOi < 0) {
                    $buildup = 'Cover';
                } else {
                    $buildup = 'Unwind';
                }

                $rows[] = [
                    'instrument_type' => $current->instrument_type,
                    'instrument_key'  => $ik,
                    'strike'          => $current->strike,
                    'current_close'   => (float) $current->close,
                    'current_oi'      => (int)   $current->open_interest,
                    'buildup'         => $buildup,
                    'delta_oi'        => $deltaOi,
                    'delta_price'     => $deltaClose,
                    'timestamp'       => $current->timestamp,
                ];
            }

            // Sort by absolute delta OI descending, take top N
            usort($rows, fn($a, $b) => abs($b['delta_oi']) <=> abs($a['delta_oi']));
            $datasets[$intervalMinutes] = array_slice($rows, 0, $limit);
        }

        return view('oi-buildup.index', [
            'filters'     => [
                'underlying_symbol' => $validated['underlying_symbol'] ?? '',
                'expiry'            => $validated['expiry'] ?? '',
                'instrument_type'   => $validated['instrument_type'] ?? '',
                'interval'          => $validated['interval'] ?? '',
                'at'                => $at,
                'limit'             => $limit,
            ],
            'datasets'    => $datasets,
            'oiThreshold' => $this->amountForToday(),
        ]);
    }

    public function expiries(Request $request)
    {
        $request->validate([
            'underlying_symbol' => 'required|string',
            'at'                => 'required|date_format:Y-m-d\TH:i',
        ]);

        $symbol = $request->underlying_symbol;
        $at     = Carbon::createFromFormat('Y-m-d\TH:i', $request->at);
        $date   = $at->toDateString();

        $expiry = DB::table('expired_expiries')
                    ->where('instrument_type', 'OPT')
                    ->whereDate('expiry_date', '>=', $date)
                    ->orderBy('expiry_date')
                    ->limit(1)
                    ->value('expiry_date');

        return response()->json(['expiry' => $expiry]);
    }

    public function amountForToday(): int
    {
        $day = Carbon::now()->format('l');

        return match ($day) {
            'Monday'    => 800000,
            'Tuesday'   => 1000000,
            'Wednesday' => 500000,
            'Thursday'  => 600000,
            'Friday'    => 700000,
            default     => 1000000,
        };
    }
}
