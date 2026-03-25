<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\BiasSnapshot;

class SaveBiasSnapshot extends Command
{
    protected $signature   = 'bias:snapshot {symbol=NIFTY} {--strikes=3}';
    protected $description = 'Compute bias from option_chains and store in bias_snapshots';

    public function handle(): void
    {
        $symbol  = strtoupper($this->argument('symbol'));
        $strikes = (int) $this->option('strikes');
        $date    = Carbon::today()->toDateString();

        // ── 1. Get current expiry (same as controller) ────────────────
        $expiry = DB::table('nse_expiries')
                    ->where('trading_symbol', $symbol)
                    ->where('instrument_type', 'OPT')
                    ->where('is_current', 1)
                    ->first();

        if (! $expiry) {
            $this->warn("No active expiry found for $symbol. Skipping.");
            return;
        }

        $expiryDate = $expiry->expiry_date;

        // ── 2. Get latest spot price ───────────────────────────────────
        $latest = DB::table('option_chains')
                    ->where('trading_symbol', $symbol)
                    ->where('expiry', $expiryDate)
                    ->orderByDesc('captured_at')
                    ->first(['underlying_spot_price']);

        if (! $latest) {
            $this->warn("No option chain data found for $symbol / $expiryDate. Skipping.");
            return;
        }

        $spotPrice     = $latest->underlying_spot_price;
        $nearestStrike = round($spotPrice / 50) * 50;

        // ── 3. Build strike list ───────────────────────────────────────
        $strikeList = [];
        for ($i = -$strikes; $i <= $strikes; $i++) {
            $strikeList[] = $nearestStrike + ($i * 50);
        }

        // ── 4. Fetch option_chains rows (same as controller) ──────────
        $startTime = $date . ' 09:15:00';
        $endTime   = $date . ' 15:30:00';

        $rows = DB::table('option_chains')
                  ->where('trading_symbol', $symbol)
                  ->where('expiry', $expiryDate)
                  ->whereIn('strike_price', $strikeList)
                  ->whereBetween('captured_at', [$startTime, $endTime])
                  ->orderBy('captured_at')
                  ->get(['strike_price', 'option_type', 'diff_oi', 'diff_volume', 'diff_ltp', 'build_up', 'captured_at']);

        if ($rows->isEmpty()) {
            $this->warn("No rows found in option_chains for $symbol. Skipping.");
            return;
        }

        // ── 5. Compute buildUpTotals (exact same logic as controller) ──
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
            $diffOi  = $row->diff_oi  ?? 0;
            $diffLtp = $row->diff_ltp ?? 0;
            $diffVol = $row->diff_volume ?? 0;
            $type    = $row->option_type;

            $buildUp = $row->build_up ?? $this->classifyBuildUp($diffOi, $diffLtp);

            if ($buildUp && isset($buildUpTotals[$type][$buildUp])) {
                $buildUpTotals[$type][$buildUp]['oi']     += abs($diffOi);
                $buildUpTotals[$type][$buildUp]['volume'] += abs($diffVol);
            }
        }

        // ── 6. Compute bias score (exact same as controller) ──────────
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

        $totalVolume = array_sum(array_column($buildUpTotals['CE'], 'volume'))
                       + array_sum(array_column($buildUpTotals['PE'], 'volume'));

        // ── 7. Save to BiasSnapshot ────────────────────────────────────
        BiasSnapshot::create([
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
            'captured_at'        => now(),
        ]);
        $this->info("✅ Snapshot saved for $symbol | Score: $biasScore | Bias: $bias ($biasStrength) | " . now()->format('H:i:s'));
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
