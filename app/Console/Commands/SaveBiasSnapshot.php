<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\BiasSnapshot;
use App\Services\SnapshotPredictionService;

class SaveBiasSnapshot extends Command
{
    protected $signature   = 'bias:snapshot {symbol=NIFTY} {--strikes=3}';
    protected $description = 'Compute bias from option_chains and store in bias_snapshots';

    public function __construct(
        protected SnapshotPredictionService $predictionService,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $symbol  = strtoupper($this->argument('symbol'));
        $strikes = (int) $this->option('strikes');
        $date    = Carbon::today()->toDateString();

        // ── 1. Get current expiry ──────────────────────────────────────
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (! $expiry) {
            $this->warn("⚠️  No active expiry found for $symbol. Skipping.");
            return;
        }

        $expiryDate = $expiry->expiry_date;

        // ── 2. Get latest spot price ───────────────────────────────────
        $latestChain = DB::table('option_chains')
                         ->where('trading_symbol', $symbol)
                         ->where('expiry', $expiryDate)
                         ->orderByDesc('captured_at')
                         ->first(['underlying_spot_price', 'captured_at']);

        if (! $latestChain) {
            $this->warn("⚠️  No option chain data found for $symbol / $expiryDate. Skipping.");
            return;
        }

        $spotPrice     = $latestChain->underlying_spot_price;
        $nearestStrike = round($spotPrice / 50) * 50;

        // ── 3. Build strike list ───────────────────────────────────────
        $strikeList = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // ── 4. Find the latest captured_at within today's session ──────
        $startTime = $date . ' 09:15:00';
        $endTime   = $date . ' 15:30:00';

        $latestCapturedAt = DB::table('option_chains')
                              ->where('trading_symbol', $symbol)
                              ->where('expiry', $expiryDate)
                              ->whereIn('strike_price', $strikeList)
                              ->whereBetween('captured_at', [$startTime, $endTime])
                              ->max('captured_at');

        if (! $latestCapturedAt) {
            $this->warn("⚠️  No option chain data found within market hours for $symbol. Skipping.");
            return;
        }

        // ── 5. ✅ Fetch ONLY the latest 5-min candle rows (±30s window) ─
        $windowStart = Carbon::parse($latestCapturedAt)->subSeconds(30)->toDateTimeString();
        $windowEnd   = Carbon::parse($latestCapturedAt)->addSeconds(30)->toDateTimeString();

        $rows = DB::table('option_chains')
                  ->where('trading_symbol', $symbol)
                  ->where('expiry', $expiryDate)
                  ->whereIn('strike_price', $strikeList)
                  ->whereBetween('captured_at', [$windowStart, $windowEnd])
                  ->orderBy('captured_at')
                  ->get(['strike_price', 'option_type', 'diff_oi', 'diff_volume', 'diff_ltp', 'build_up', 'captured_at']);

        if ($rows->isEmpty()) {
            $this->warn("⚠️  No rows found in the latest 5-min window for $symbol. Skipping.");
            return;
        }

        $this->line("📦 Processing " . $rows->count() . " rows for $symbol @ " . Carbon::parse($latestCapturedAt)->format('H:i:s'));

        // ── 6. Compute buildUpTotals ───────────────────────────────────
        $buildUpTotals = [
            'CE' => [
                'Long Build'  => ['oi' => 0, 'volume' => 0],
                'Short Build' => ['oi' => 0, 'volume' => 0],
                'Short Cover' => ['oi' => 0, 'volume' => 0],
                'Long Unwind' => ['oi' => 0, 'volume' => 0],
            ],
            'PE' => [
                'Long Build'  => ['oi' => 0, 'volume' => 0],
                'Short Build' => ['oi' => 0, 'volume' => 0],
                'Short Cover' => ['oi' => 0, 'volume' => 0],
                'Long Unwind' => ['oi' => 0, 'volume' => 0],
            ],
        ];

        foreach ($rows as $row) {
            $diffOi  = $row->diff_oi     ?? 0;
            $diffLtp = $row->diff_ltp    ?? 0;
            $diffVol = $row->diff_volume ?? 0;
            $type    = $row->option_type;

            $buildUp = $row->build_up ?? $this->classifyBuildUp($diffOi, $diffLtp);

            if ($buildUp && isset($buildUpTotals[$type][$buildUp])) {
                $buildUpTotals[$type][$buildUp]['oi']     += abs($diffOi);
                $buildUpTotals[$type][$buildUp]['volume'] += abs($diffVol);
            }
        }

        // ── 7. Compute bias score ──────────────────────────────────────
        $bullishOI =
            ($buildUpTotals['CE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['CE']['Short Cover']['oi'] * 1) +
            ($buildUpTotals['PE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['PE']['Long Unwind']['oi'] * 1);

        $bearishOI =
            ($buildUpTotals['CE']['Short Build']['oi'] * 2) +
            ($buildUpTotals['CE']['Long Unwind']['oi'] * 1) +
            ($buildUpTotals['PE']['Long Build']['oi']  * 2) +
            ($buildUpTotals['PE']['Short Cover']['oi'] * 1);

        $totalWeightedOI = $bullishOI + $bearishOI;

        $biasScore = $totalWeightedOI > 0
            ? round((($bullishOI - $bearishOI) / $totalWeightedOI) * 100)
            : 0;

        $bias = match (true) {
            $biasScore > 20  => 'Bullish',
            $biasScore < -20 => 'Bearish',
            default          => 'Sideways',
        };

        $biasStrength = match (true) {
            abs($biasScore) >= 60 => 'Strong',
            abs($biasScore) >= 35 => 'Moderate',
            default               => 'Weak',
        };

        // ── 8. ✅ Fixed totalVolume — collect()->sum() not array_column ─
        $totalVolume = collect($buildUpTotals['CE'])->sum('volume')
                       + collect($buildUpTotals['PE'])->sum('volume');

        // ── 9. Save BiasSnapshot ───────────────────────────────────────
        $saved = BiasSnapshot::create([
            'trading_symbol'     => $symbol,
            'date'               => $date,
            'expiry_date'        => $expiryDate,
            'spot_price'         => $spotPrice,
            'atm_strike'         => $nearestStrike,
            'strikes_range'      => $strikes,
            'bias_score'         => $biasScore,
            'bias'               => $bias,
            'bias_strength'      => $biasStrength,

            'ce_long_build_oi'   => $buildUpTotals['CE']['Long Build']['oi'],
            'ce_short_build_oi'  => $buildUpTotals['CE']['Short Build']['oi'],
            'ce_short_cover_oi'  => $buildUpTotals['CE']['Short Cover']['oi'],
            'ce_long_unwind_oi'  => $buildUpTotals['CE']['Long Unwind']['oi'],

            'ce_long_build_vol'  => $buildUpTotals['CE']['Long Build']['volume'],
            'ce_short_build_vol' => $buildUpTotals['CE']['Short Build']['volume'],
            'ce_short_cover_vol' => $buildUpTotals['CE']['Short Cover']['volume'],
            'ce_long_unwind_vol' => $buildUpTotals['CE']['Long Unwind']['volume'],

            'pe_long_build_oi'   => $buildUpTotals['PE']['Long Build']['oi'],
            'pe_short_build_oi'  => $buildUpTotals['PE']['Short Build']['oi'],
            'pe_short_cover_oi'  => $buildUpTotals['PE']['Short Cover']['oi'],
            'pe_long_unwind_oi'  => $buildUpTotals['PE']['Long Unwind']['oi'],

            'pe_long_build_vol'  => $buildUpTotals['PE']['Long Build']['volume'],
            'pe_short_build_vol' => $buildUpTotals['PE']['Short Build']['volume'],
            'pe_short_cover_vol' => $buildUpTotals['PE']['Short Cover']['volume'],
            'pe_long_unwind_vol' => $buildUpTotals['PE']['Long Unwind']['volume'],

            'bullish_oi'         => $bullishOI,
            'bearish_oi'         => $bearishOI,
            'total_volume'       => $totalVolume,

            // ✅ Use actual candle time, not now()
            'captured_at'        => Carbon::parse($latestCapturedAt),
        ]);

        $this->info("✅ Snapshot saved  | $symbol | Score: $biasScore | $bias ($biasStrength) | " . Carbon::parse($latestCapturedAt)->format('H:i:s'));

        // ── 10. ✅ Run strategies on saved snapshot ────────────────────
        $history = BiasSnapshot::where('trading_symbol', $symbol)
                               ->where('date', $date)
                               ->orderBy('captured_at')
                               ->get();

        $strategies  = $this->predictionService->predict($saved, $history);
        $aggregate   = $this->predictionService->aggregate($strategies);
        $session     = $this->predictionService->evaluateSession($symbol);

        $triggered = collect($strategies)->where('triggered', true)->count();

        $this->info("📊 Prediction     | Signal: {$aggregate['signal']} | Confidence: {$aggregate['confidence']}% | $triggered/6 triggered");
        $this->info("📅 Session        | Dominant: {$session['dominant_signal']} | Current: {$session['current_signal']} | State: {$session['trend_state']}");
        $this->line("📈 Vote Tally     | Bullish: {$session['bullish_count']} | Bearish: {$session['bearish_count']} | Sideways: {$session['sideways_count']} | Phase: {$session['session_phase']}");
    }

    private function classifyBuildUp(int|float $diffOi, int|float $diffLtp): ?string
    {
        if ($diffOi == 0 || $diffLtp == 0) return null;
        if ($diffOi > 0 && $diffLtp > 0)  return 'Long Build';
        if ($diffOi > 0 && $diffLtp < 0)  return 'Short Build';
        if ($diffOi < 0 && $diffLtp < 0)  return 'Long Unwind';
        if ($diffOi < 0 && $diffLtp > 0)  return 'Short Cover';
        return null;
    }
}
