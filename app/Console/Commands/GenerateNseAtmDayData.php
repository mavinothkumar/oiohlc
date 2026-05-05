<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateNseAtmDayData extends Command
{
    protected $signature = 'nse:generate-atm-day-data
                            {--symbol=NIFTY : Underlying symbol}
                            {--interval=day : OHLC interval to use from expired_ohlc}
                            {--from= : Start working_date (Y-m-d)}
                            {--to= : End working_date (Y-m-d)}
                            {--date= : Single working_date (Y-m-d)}
                            {--force : Rebuild existing rows}';

    protected $description = 'Generate ATM day data from nse_working_days, expired_ohlc and expired_expiries';

    public function handle(): int
    {
        $symbol = strtoupper((string) $this->option('symbol'));
        $interval = (string) $this->option('interval');
        $force = (bool) $this->option('force');

        $workingDays = DB::table('nse_working_days')
                         ->when($this->option('date'), fn ($q, $date) => $q->whereDate('working_date', $date))
                         ->when($this->option('from'), fn ($q, $from) => $q->whereDate('working_date', '>=', $from))
                         ->when($this->option('to'), fn ($q, $to) => $q->whereDate('working_date', '<=', $to))
                         ->orderBy('working_date')
                         ->get(['working_date']);

        if ($workingDays->isEmpty()) {
            $this->warn('No working days found for the given filters.');
            return self::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;

        foreach ($workingDays as $day) {
            $currentDate = Carbon::parse($day->working_date)->toDateString();
            $previousDate = $this->getPreviousTradingDay($currentDate);
            if (! $previousDate) {
                $this->line("Skipping {$currentDate}: previous trading day not found in nse_working_days.");
                $skipped++;
                continue;
            }

            if (! $force) {
                $exists = DB::table('nse_atm_day_data')
                            ->where('underlying_symbol', $symbol)
                            ->whereDate('current_date', $currentDate)
                            ->exists();

                if ($exists) {
                    $this->line("Skipping {$currentDate}: row already exists.");
                    $skipped++;
                    continue;
                }
            }

            $expiryContext = $this->resolveExpiryContext($symbol, $previousDate);

            if (! $expiryContext) {
                $this->line("Skipping {$currentDate}: expiry not found for previous date {$previousDate}.");
                $skipped++;
                continue;
            }

            $indexPrev = $this->getIndexOhlc($symbol, $previousDate, $interval);

            if (! $indexPrev) {
                $this->line("Skipping {$currentDate}: previous day INDEX OHLC missing for expiry {$expiryContext['current_expiry_date']}.");
                $skipped++;
                continue;
            }

            $atmPair = $this->findAtmPair($symbol, $previousDate, $expiryContext['current_expiry_date'], $interval);

            if (! $atmPair) {
                $this->line("Skipping {$currentDate}: CE/PE pair not found to compute ATM strike.");
                $skipped++;
                continue;
            }


            $currentIndexOpen = $this->getIndexOhlc($symbol, $currentDate, $interval);
            $midPoint = round((((float) $atmPair->ce_close) + ((float) $atmPair->pe_close)) / 2, 2);

            DB::table('nse_atm_day_data')->updateOrInsert(
                [
                    'underlying_symbol' => $symbol,
                    'current_date' => $currentDate,
                ],
                [
                    'previous_date' => $previousDate,
                    'atm_strike' => $atmPair->strike,
                    'mid_point' => $midPoint,
                    'current_expiry_date' => $expiryContext['current_expiry_date'],
                    'next_expiry_date' => $expiryContext['next_expiry_date'],
                    'current_day_index_open' => $currentIndexOpen?->open,
                    'previous_day_index_open' => $indexPrev->open,
                    'previous_day_index_high' => $indexPrev->high,
                    'previous_day_index_low' => $indexPrev->low,
                    'previous_day_index_close' => $indexPrev->close,
                    'previous_day_ce_close' => $atmPair->ce_close,
                    'previous_day_pe_close' => $atmPair->pe_close,
                    'previous_day_ce_high' => $atmPair->ce_high,
                    'previous_day_pe_high' => $atmPair->pe_high,
                    'previous_day_ce_low' => $atmPair->ce_low,
                    'previous_day_pe_low' => $atmPair->pe_low,
                    'is_expiry_day_rollover' => $expiryContext['is_rollover_expiry'] ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $processed++;
            $this->info("Processed {$currentDate} using previous trading day {$previousDate} and expiry {$expiryContext['current_expiry_date']}.");
        }

        $this->newLine();
        $this->info("Completed. Processed: {$processed}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    protected function getPreviousTradingDay(string $currentDate): ?string
    {
        return DB::table('nse_working_days')
                 ->whereDate('working_date', '<', $currentDate)
                 ->orderByDesc('working_date')
                 ->value('working_date');
    }

    protected function resolveExpiryContext(string $symbol, string $previousDate): ?array
    {
        $currentExpiry = DB::table('expired_expiries')
                           ->where('underlying_symbol', $symbol)
                           ->where('instrument_type', 'OPT')
                           ->whereDate('expiry_date', '>=', $previousDate)
                           ->orderBy('expiry_date')
                           ->value('expiry_date');

        if (! $currentExpiry) {
            return null;
        }

        $isRollover = $currentExpiry === $previousDate;

        $effectiveExpiry = $currentExpiry;
        if ($isRollover) {
            $effectiveExpiry = DB::table('expired_expiries')
                                 ->where('underlying_symbol', $symbol)
                                 ->where('instrument_type', 'OPT')
                                 ->whereDate('expiry_date', '>', $previousDate)
                                 ->orderBy('expiry_date')
                                 ->value('expiry_date');
        }

        if (! $effectiveExpiry) {
            return null;
        }

        $nextExpiry = DB::table('expired_expiries')
                        ->where('underlying_symbol', $symbol)
                        ->where('instrument_type', 'OPT')
                        ->whereDate('expiry_date', '>', $effectiveExpiry)
                        ->orderBy('expiry_date')
                        ->value('expiry_date');

        return [
            'current_expiry_date' => $effectiveExpiry,
            'next_expiry_date' => $nextExpiry,
            'is_rollover_expiry' => $isRollover,
        ];
    }

    protected function getIndexOhlc(string $symbol, string $tradeDate, string $interval): ?object
    {
        $query = DB::table('expired_ohlc')
                   ->select('open', 'high', 'low', 'close', 'timestamp')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'INDEX')
                   ->where('interval', $interval);

        if ($interval === 'day') {
            $query->where('timestamp', $tradeDate . ' 00:00:00');
        } else {
            $query->whereDate('timestamp', $tradeDate)
                  ->orderByDesc('timestamp');
        }

        return $query->first();
    }


    protected function getCurrentDayIndexOpen(string $symbol, string $tradeDate, ?string $expiry, string $interval): ?object
    {
        if (! $expiry) {
            return null;
        }

        return DB::table('expired_ohlc')
                 ->select('open', 'timestamp')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', 'INDEX')
                 ->whereDate('expiry', $expiry)
                 ->where('interval', $interval)
                 ->whereDate('timestamp', $tradeDate)
                 ->orderBy('timestamp')
                 ->first();
    }

    protected function findAtmPair(string $symbol, string $tradeDate, string $expiry, string $interval): ?object
    {
        $query = DB::table('expired_ohlc as ce')
                   ->join('expired_ohlc as pe', function ($join) {
                       $join->on('ce.underlying_symbol', '=', 'pe.underlying_symbol')
                            ->on('ce.expiry', '=', 'pe.expiry')
                            ->on('ce.strike', '=', 'pe.strike')
                            ->on('ce.timestamp', '=', 'pe.timestamp');
                   })
                   ->selectRaw('
            ce.strike,
            ce.open as ce_open,
            ce.high as ce_high,
            ce.low as ce_low,
            ce.close as ce_close,
            pe.open as pe_open,
            pe.high as pe_high,
            pe.low as pe_low,
            pe.close as pe_close,
            ABS(ce.close - pe.close) as premium_diff,
            ce.timestamp as pair_timestamp
        ')
                   ->where('ce.underlying_symbol', $symbol)
                   ->whereDate('ce.expiry', $expiry)
                   ->where('ce.instrument_type', 'CE')
                   ->where('pe.instrument_type', 'PE')
                   ->where('ce.interval', $interval)
                   ->where('pe.interval', $interval);

        if ($interval === 'day') {
            $query->where('ce.timestamp', $tradeDate . ' 00:00:00')
                  ->where('pe.timestamp', $tradeDate . ' 00:00:00');
        } else {
            $query->whereDate('ce.timestamp', $tradeDate)
                  ->whereDate('pe.timestamp', $tradeDate)
                  ->orderByDesc('ce.timestamp');
        }

        return $query
            ->orderBy('premium_diff')
            ->orderBy('ce.strike')
            ->first();
    }
}
