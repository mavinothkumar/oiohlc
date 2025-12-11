<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RunSixLevelBacktest extends Command
{
    protected $signature = 'backtest:six-level
                            {expiry : Expiry date in Y-m-d format}
                            {--symbol=NIFTY}';

    protected $description = 'Run six levels break backtest for a given expiry date';

    public function handle(): int
    {
        $symbol = $this->option('symbol');
        $expiry = $this->argument('expiry');

        $this->info("Starting six-level backtest for {$symbol} {$expiry}");

        // All trading dates (daily candles) for this expiry, from index start
        $dates = DB::table('expired_ohlc')
                   ->selectRaw('DATE(`timestamp`) as trade_date')
                   ->where('underlying_symbol', $symbol)
                   ->whereIn('instrument_type', ['CE', 'PE'])
                   ->where('interval', 'day')
                   ->where('expiry', $expiry)
                   ->where('timestamp', '>=', '2024-09-26 00:00:00')
                   ->distinct()
                   ->orderBy('trade_date')
                   ->pluck('trade_date');

        foreach ($dates as $tradeDate) {
            $this->processTradeDate($symbol, $expiry, $tradeDate);
        }

        return Command::SUCCESS;
    }

    protected function processTradeDate(string $symbol, string $expiry, string $tradeDate): void
    {
        $this->info("Processing {$tradeDate} for {$symbol} {$expiry}");

        // Previous trading day (skip weekends/holidays implicitly)
        $prevDate = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->whereIn('instrument_type', ['CE', 'PE'])
                      ->where('interval', 'day')
                      ->where('expiry', $expiry)
                      ->whereRaw('DATE(`timestamp`) < ?', [$tradeDate])
                      ->max(DB::raw('DATE(`timestamp`)'));

        if (!$prevDate) {
            $this->warn("  Skipped {$tradeDate}: no previous trading day.");
            return;
        }

        // ATM strike from previous day using CE+PE close sum
        $atmStrikeRow = DB::table('expired_ohlc')
                          ->select('strike', DB::raw('SUM(close) as sum_close'))
                          ->where('underlying_symbol', $symbol)
                          ->whereIn('instrument_type', ['CE', 'PE'])
                          ->where('interval', 'day')
                          ->where('expiry', $expiry)
                          ->whereRaw('DATE(`timestamp`) = ?', [$prevDate])
                          ->groupBy('strike')
                          ->orderBy('sum_close')
                          ->first();

        if (!$atmStrikeRow) {
            $this->warn("  Skipped {$tradeDate}: no ATM strike row.");
            return;
        }

        $atmStrike = $atmStrikeRow->strike;

        // Previous-day CE/PE OHLC for ATM strike
        $prevRows = DB::table('expired_ohlc')
                      ->select('instrument_type', 'instrument_key', 'open', 'high', 'low', 'close')
                      ->where('underlying_symbol', $symbol)
                      ->where('expiry', $expiry)
                      ->where('interval', 'day')
                      ->whereRaw('DATE(`timestamp`) = ?', [$prevDate])
                      ->where('strike', $atmStrike)
                      ->whereIn('instrument_type', ['CE', 'PE'])
                      ->get()
                      ->keyBy('instrument_type');

        if (!$prevRows->has('CE') || !$prevRows->has('PE')) {
            $this->warn("  Skipped {$tradeDate}: missing CE/PE daily data for ATM.");
            return;
        }

        $cePrev = $prevRows->get('CE');
        $pePrev = $prevRows->get('PE');

        $cePrevLow   = (float) $cePrev->low;
        $pePrevLow   = (float) $pePrev->low;
        $cePrevHigh  = (float) $cePrev->high;
        $pePrevHigh  = (float) $pePrev->high;
        $cePrevClose = (float) $cePrev->close;
        $pePrevClose = (float) $pePrev->close;

        $lowestPrevLow = min($cePrevLow, $pePrevLow);
        $lowestPrevLowSide = $cePrevLow === $pePrevLow
            ? 'BOTH'
            : ($cePrevLow < $pePrevLow ? 'CE' : 'PE');

        // Get all 5-min candles for the whole day for this strike (CE+PE) once
        $fiveMinRows = DB::table('expired_ohlc')
                         ->where('underlying_symbol', $symbol)
                         ->where('expiry', $expiry)
                         ->where('strike', $atmStrike)
                         ->where('interval', '5minute')
                         ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                         ->whereIn('instrument_type', ['CE', 'PE'])
                         ->orderBy('timestamp')
                         ->get();

        $ceFive = $fiveMinRows->where('instrument_type', 'CE')->values();
        $peFive = $fiveMinRows->where('instrument_type', 'PE')->values();

        if ($ceFive->isEmpty() && $peFive->isEmpty()) {
            $this->warn("  Skipped {$tradeDate}: no 5min data for ATM.");
            return;
        }

        // First break of lowestPrevLow for CE and PE
        $ceBreak = $ceFive->first(function ($row) use ($lowestPrevLow) {
            return (float) $row->low <= $lowestPrevLow;
        });

        $peBreak = $peFive->first(function ($row) use ($lowestPrevLow) {
            return (float) $row->low <= $lowestPrevLow;
        });

        // Determine which side(s) broke
        $sixBrokenSide = 'NONE';
        if ($ceBreak && $peBreak) {
            $sixBrokenSide = 'BOTH';
        } elseif ($ceBreak) {
            $sixBrokenSide = 'CE';
        } elseif ($peBreak) {
            $sixBrokenSide = 'PE';
        }

        // Base payload
        $payload = [
            'underlying_symbol'     => $symbol,
            'expiry'                => $expiry,
            'trade_date'            => $tradeDate,
            'prev_trade_date'       => $prevDate,
            'atm_strike'            => $atmStrike,
            'ce_instrument_key'     => $cePrev->instrument_key,
            'pe_instrument_key'     => $pePrev->instrument_key,
            'ce_prev_low'           => $cePrevLow,
            'pe_prev_low'           => $pePrevLow,
            'lowest_prev_low'       => $lowestPrevLow,
            'lowest_prev_low_side'  => $lowestPrevLowSide,
            'six_level_broken_side' => $sixBrokenSide,
            'ce_break_time'         => $ceBreak->timestamp ?? null,
            'pe_break_time'         => $peBreak->timestamp ?? null,
            'ce_break_open'         => $ceBreak->open ?? null,
            'ce_break_high'         => $ceBreak->high ?? null,
            'ce_break_low'          => $ceBreak->low ?? null,
            'ce_break_close'        => $ceBreak->close ?? null,
            'ce_break_volume'       => $ceBreak->volume ?? null,
            'ce_break_oi'           => $ceBreak->open_interest ?? null,
            'pe_break_open'         => $peBreak->open ?? null,
            'pe_break_high'         => $peBreak->high ?? null,
            'pe_break_low'          => $peBreak->low ?? null,
            'pe_break_close'        => $peBreak->close ?? null,
            'pe_break_volume'       => $peBreak->volume ?? null,
            'pe_break_oi'           => $peBreak->open_interest ?? null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ];
        // Extended metrics default values
        $extended = [
            // CE opponent (PE) status
            'ce_opponent_prev_high_broken'       => false,
            'ce_opponent_prev_high_break_time'   => null,
            'ce_opponent_prev_high_break_price'  => null,
            'ce_opponent_prev_close_crossed'     => false,
            'ce_opponent_prev_close_cross_time'  => null,
            'ce_opponent_prev_close_cross_price' => null,

            // PE opponent (CE) status
            'pe_opponent_prev_high_broken'       => false,
            'pe_opponent_prev_high_break_time'   => null,
            'pe_opponent_prev_high_break_price'  => null,
            'pe_opponent_prev_close_crossed'     => false,
            'pe_opponent_prev_close_cross_time'  => null,
            'pe_opponent_prev_close_cross_price' => null,

            // CE post-break
            'ce_low_retested'                    => false,
            'ce_low_retest_time'                 => null,
            'ce_low_retest_price'                => null,
            'ce_retest_distance_from_low'        => null,
            'ce_max_high_from_low'               => null,
            'ce_max_high_from_low_time'          => null,

            // PE post-break
            'pe_low_retested'                    => false,
            'pe_low_retest_time'                 => null,
            'pe_low_retest_price'                => null,
            'pe_retest_distance_from_low'        => null,
            'pe_max_high_from_low'               => null,
            'pe_max_high_from_low_time'          => null,
        ];
        // --- CE as breaker: check PE opponent and CE post-break behaviour ---
        if ($ceBreak) {
            $ceBreakTime   = Carbon::parse($ceBreak->timestamp);
            $peBeforeCe    = $peFive->filter(fn ($row) => Carbon::parse($row->timestamp)->lte($ceBreakTime));
            $peAfterCe     = $peFive->filter(fn ($row) => Carbon::parse($row->timestamp)->gt($ceBreakTime));

            // Opponent PE previous-day HIGH broken? (close > prev high)
            $peHighBreak = $peBeforeCe->first(function ($row) use ($pePrevHigh) {
                return (float) $row->close > $pePrevHigh;
            });

            if ($peHighBreak) {
                $extended['ce_opponent_prev_high_broken']       = true;
                $extended['ce_opponent_prev_high_break_time']   = $peHighBreak->timestamp;
                $extended['ce_opponent_prev_high_break_price']  = $peHighBreak->close;
            }

            // Opponent PE previous-day CLOSE crossed? (close > prev close)
            $peCloseCross = $peBeforeCe->first(function ($row) use ($pePrevClose) {
                return (float) $row->close > $pePrevClose;
            });

            if ($peCloseCross) {
                $extended['ce_opponent_prev_close_crossed']     = true;
                $extended['ce_opponent_prev_close_cross_time']  = $peCloseCross->timestamp;
                $extended['ce_opponent_prev_close_cross_price'] = $peCloseCross->close;
            }

            // CE post-break: retest and max-high from CE's own day-low
            $ceDayLowestLow = $ceFive->min(fn ($row) => (float) $row->low);

            $ceRetest = $ceAfterCe = $ceFive->filter(fn ($row) => Carbon::parse($row->timestamp)->gt($ceBreakTime))
                                            ->first(function ($row) use ($lowestPrevLow) {
                                                return (float) $row->close > $lowestPrevLow;
                                            });

            if ($ceRetest) {
                $retestPrice = (float) $ceRetest->close;
                $extended['ce_low_retested']             = true;
                $extended['ce_low_retest_time']          = $ceRetest->timestamp;
                $extended['ce_low_retest_price']         = $retestPrice;
                // distance from CE's own intraday lowest low
                $extended['ce_retest_distance_from_low'] = $retestPrice - $ceDayLowestLow;
            }

            // Max high from CE's own day-low after break
            $ceAfterBreak = $ceFive->filter(fn ($row) => Carbon::parse($row->timestamp)->gt($ceBreakTime));
            $ceMaxHighRow = $ceAfterBreak->sortByDesc(fn ($row) => (float) $row->high)->first();

            if ($ceMaxHighRow) {
                // store absolute distance from CE's day-low
                $extended['ce_max_high_from_low']      = (float) $ceMaxHighRow->high - $ceDayLowestLow;
                $extended['ce_max_high_from_low_time'] = $ceMaxHighRow->timestamp;
            }
        }

        // --- PE as breaker: check CE opponent and PE post-break behaviour ---
        if ($peBreak) {
            $peBreakTime   = Carbon::parse($peBreak->timestamp);
            $ceBeforePe    = $ceFive->filter(fn ($row) => Carbon::parse($row->timestamp)->lte($peBreakTime));
            $ceAfterPe     = $ceFive->filter(fn ($row) => Carbon::parse($row->timestamp)->gt($peBreakTime));

            // Opponent CE previous-day HIGH broken? (close > prev high)
            $ceHighBreak = $ceBeforePe->first(function ($row) use ($cePrevHigh) {
                return (float) $row->close > $cePrevHigh;
            });

            if ($ceHighBreak) {
                $extended['pe_opponent_prev_high_broken']       = true;
                $extended['pe_opponent_prev_high_break_time']   = $ceHighBreak->timestamp;
                $extended['pe_opponent_prev_high_break_price']  = $ceHighBreak->close;
            }

            // Opponent CE previous-day CLOSE crossed? (close > prev close)
            $ceCloseCross = $ceBeforePe->first(function ($row) use ($cePrevClose) {
                return (float) $row->close > $cePrevClose;
            });

            if ($ceCloseCross) {
                $extended['pe_opponent_prev_close_crossed']     = true;
                $extended['pe_opponent_prev_close_cross_time']  = $ceCloseCross->timestamp;
                $extended['pe_opponent_prev_close_cross_price'] = $ceCloseCross->close;
            }

            // PE post-break: retest and max-high from PE's own day-low
            $peDayLowestLow = $peFive->min(fn ($row) => (float) $row->low);

            $peRetest = $peAfterPe = $peFive->filter(fn ($row) => Carbon::parse($row->timestamp)->gt($peBreakTime))
                                            ->first(function ($row) use ($lowestPrevLow) {
                                                return (float) $row->close > $lowestPrevLow;
                                            });

            if ($peRetest) {
                $retestPrice = (float) $peRetest->close;
                $extended['pe_low_retested']             = true;
                $extended['pe_low_retest_time']          = $peRetest->timestamp;
                $extended['pe_low_retest_price']         = $retestPrice;
                // distance from PE's own intraday lowest low
                $extended['pe_retest_distance_from_low'] = $retestPrice - $peDayLowestLow;
            }

            $peAfterBreak = $peFive->filter(fn ($row) => Carbon::parse($row->timestamp)->gt($peBreakTime));
            $peMaxHighRow = $peAfterBreak->sortByDesc(fn ($row) => (float) $row->high)->first();

            if ($peMaxHighRow) {
                $extended['pe_max_high_from_low']      = (float) $peMaxHighRow->high - $peDayLowestLow;
                $extended['pe_max_high_from_low_time'] = $peMaxHighRow->timestamp;
            }
        }

        $this->info(sprintf(
            '[%s | %s | %s] ATM %s -> six_level_broken_side = %s',
            $symbol,
            $expiry,
            $tradeDate,
            $atmStrike,
            $sixBrokenSide
        ));

        DB::table('six_level_backtests')->insert(array_merge($payload, $extended));
    }
}

