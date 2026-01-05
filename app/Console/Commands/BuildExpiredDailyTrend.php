<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DailyTrend;

class BuildExpiredDailyTrend extends Command
{
    protected $signature = 'trend:build
                            {--backtest : Use expired_ohlc instead of DailyOhlcQuote/Upstox}
                            {--from= : Backtest start date YYYY-MM-DD}
                            {--to= : Backtest end date YYYY-MM-DD}';

    protected $description = 'Populate / backfill daily_trend and compute earth levels from live or expired OHLC';

    public function handle(): int
    {
        return $this->runBacktest();
    }

    /* ============================================================
     * BACKTEST MODE (using expired_ohlc only)
     * ============================================================
     */
    private function runBacktest(): int
    {
        $from = $this->option('from');
        $to   = $this->option('to') ?: $from;

        if (! $from) {
            $this->error('For backtest, please provide --from=YYYY-MM-DD (and optionally --to=YYYY-MM-DD)');
            return 1;
        }

        $workingDays = $this->dateRange($from, $to);

        if (empty($workingDays)) {
            $this->warn("No working days found between {$from} and {$to} in nse_working_days.");
            return 0;
        }

        $symbols = ['NIFTY'];//, 'BANKNIFTY', 'SENSEX'

        foreach ($workingDays as $d) {
            $saved = 0;
            foreach ($symbols as $symbol) {

                $prevDate = $this->getPreviousWorkingDate($d);
                if (! $prevDate) {
                    $this->warn("No previous working day for {$d}");
                    continue;
                }

                // Build levels from previous working day data
                $data = $this->buildFromPreviousDay($prevDate, $symbol);
                if (! $data) {
                    continue;
                }

                // Now attach today's open and earth bands
                $openPrice = $this->getBacktestOpenFromExpired($d, $symbol);
                if ($openPrice !== null) {
                    $earthValue                     = (float) $data['earth_value'];
                    $data['current_day_index_open'] = $openPrice;
                    $data['earth_high']             = $openPrice + $earthValue;
                    $data['earth_low']              = $openPrice - $earthValue;
                }

                // VERY IMPORTANT: quote_date is today (D), NOT prevDate
                $data['quote_date']  = $d;
                $data['symbol_name'] = $symbol;

                DailyTrend::updateOrCreate(
                    ['quote_date' => $d, 'symbol_name' => $symbol],
                    $data
                );

                $saved++;
            }

            $this->info("BACKTEST mode: Saved/updated {$saved} records for working date {$d}");
        }

        return 0;
    }

    private function getCurrentWeekExpiry(string $symbol, string $prevDate): ?string
    {
        // Take the nearest future expiry as on prevDate for that symbol
        return DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->whereIn('instrument_type', ['CE', 'PE'])
                 ->where('interval', 'day')
                 ->whereDate('timestamp', $prevDate)
                 ->whereDate('expiry', '>=', $this->option('from'))
                 ->orderBy('expiry')
                 ->value('expiry');
    }

    private function buildFromPreviousDay(string $prevDate, string $symbol): ?array
    {
        // 1) previous day index OHLC (daily candle)
        $indexRow = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->where('instrument_type', 'INDEX')
                      ->where('interval', 'day') // adjust if needed
                      ->whereDate('timestamp', $prevDate)
                      ->first();

        if (! $indexRow) {
            return null;
        }

        // 2) current week expiry as on prevDate
        $currentExpiry = $this->getCurrentWeekExpiry($symbol, $prevDate);
        if (! $currentExpiry) {
            return null;
        }

        // 3) previous day options for that fixed expiry
        $options = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->whereIn('instrument_type', ['CE', 'PE'])
                     ->where('interval', 'day')
                     ->whereDate('timestamp', $prevDate)
                     ->whereDate('expiry', $currentExpiry) // fixed week expiry
                     ->get();

        if ($options->isEmpty()) {
            return null;
        }

        return $this->buildTrendFromOhlc(
            $indexRow->high,
            $indexRow->low,
            $indexRow->close,
            $options,
            $symbol,
            $currentExpiry
        );
    }

    private function buildTrendFromOhlc(
        float $indexHigh,
        float $indexLow,
        float $indexClose,
        $options,
        string $symbol,
        $expiryDate
    ): ?array {
        $bestPair = $this->findBestPair($options);
        if (! $bestPair) {
            return null;
        }

        $strike   = $bestPair['strike'];
        $ce       = $bestPair['ce'];
        $pe       = $bestPair['pe'];
        $ceClose  = $ce->close;
        $peClose  = $pe->close;
        $sumClose = $ceClose + $peClose;

        $earthValue = ($indexHigh - $indexLow) * 0.2611;

        $ceType     = $this->computeType($ce, $symbol);
        $peType     = $this->computeType($pe, $symbol);
        $marketType = $this->computeMarketType($ceType, $peType);

        return [
            'index_high'  => $indexHigh,       // previous working day high
            'index_low'   => $indexLow,        // previous working day low
            'index_close' => $indexClose,      // previous working day close
            'earth_value' => $earthValue,      // from previous day range

            'strike'      => $strike,
            'ce_high'     => $ce->high,
            'ce_low'      => $ce->low,
            'ce_close'    => $ceClose,
            'pe_high'     => $pe->high,
            'pe_low'      => $pe->low,
            'pe_close'    => $peClose,
            'min_r'       => $strike + $ceClose,
            'min_s'       => $strike - $peClose,
            'max_r'       => $strike + $sumClose,
            'max_s'       => $strike - $sumClose,

            'expiry_date' => $expiryDate,
            'market_type' => $marketType,
            'ce_type'     => $ceType,
            'pe_type'     => $peType,
        ];
    }


    private function getBacktestOpenFromExpired(string $date, string $symbol): ?float
    {
        // Option 1: daily open
        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', 'INDEX')
                 ->where('interval', 'day')
                 ->whereDate('timestamp', $date)
                 ->orderBy('timestamp')
                 ->first();

        if ($row) {
            return (float) $row->open;
        }

        // Option 2: first 5-minute candle of the day
        $row5 = DB::table('expired_ohlc')
                  ->where('underlying_symbol', $symbol)
                  ->where('instrument_type', 'INDEX')
                  ->where('interval', '5minute')   // adjust to your exact 5-min interval name
                  ->whereDate('timestamp', $date)
                  ->orderBy('timestamp')
                  ->first();

        return $row5 ? (float) $row5->open : null;
    }

    private function getPreviousWorkingDate(string $date): ?string
    {
        return DB::table('nse_working_days')
                 ->where('working_date', '<', $date)
                 ->orderByDesc('working_date')
                 ->value('working_date'); // returns string date or null
    }

    private function getPreviousIndexCloseFromExpired(string $symbol, string $currentDate): ?float
    {
        $prevDate = $this->getPreviousWorkingDate($currentDate);
        if (! $prevDate) {
            return null;
        }

        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', 'INDEX')
                 ->where('interval', 'day') // or your daily interval name
                 ->whereDate('timestamp', $prevDate)
                 ->orderBy('timestamp', 'desc')
                 ->first();

        return $row ? (float) $row->close : null;
    }
    private function getPreviousIndexCloseFromTrend(string $symbol, string $currentDate): ?float
    {
        $prevDate = $this->getPreviousWorkingDate($currentDate);
        if (! $prevDate) {
            return null;
        }

        $trend = DailyTrend::where('symbol_name', $symbol)
                           ->whereDate('quote_date', $prevDate)
                           ->first();

        return $trend ? (float) $trend->index_close : null;
    }

    private function findBestPair($options): ?array
    {
        $groupedByStrike = collect($options)->groupBy('strike');
        $bestPair        = null;
        $bestDiff        = null;

        foreach ($groupedByStrike as $strike => $contracts) {
            $ce = $contracts->firstWhere('instrument_type', 'CE') ?? $contracts->firstWhere('option_type', 'CE');
            $pe = $contracts->firstWhere('instrument_type', 'PE') ?? $contracts->firstWhere('option_type', 'PE');

            if (! $ce || ! $pe) {
                continue;
            }

            $diff = abs($ce->close - $pe->close);
            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff = $diff;
                $bestPair = [
                    'strike' => $strike,
                    'ce'     => $ce,
                    'pe'     => $pe,
                ];
            }
        }

        return $bestPair;
    }

    private function computeType($contract, string $symbol): string
    {
        $high  = $contract->high;
        $low   = $contract->low;
        $close = $contract->close;

        $highCloseDiff = max(0, $high - $close);
        $closeLowDiff  = max(0, $close - $low);

        $sideThreshold = match ($symbol) {
            'NIFTY' => 30,
            'BANKNIFTY' => 60,
            'SENSEX' => 90,
            default => 30,
        };

        if ($highCloseDiff > $closeLowDiff) {
            $type = 'Profit';
        } elseif ($highCloseDiff < $closeLowDiff) {
            $type = 'Panic';
        } else {
            $type = 'Side';
        }

        $minDiff = min($highCloseDiff, $closeLowDiff);
        if ($minDiff > $sideThreshold && $type !== 'Side') {
            $type .= ' Side';
        }

        return $type;
    }

    private function computeMarketType(string $ceType, string $peType): string
    {
        if (str_starts_with($ceType, 'Panic') && str_starts_with($peType, 'Panic')) {
            return 'Both Panic';
        }

        if (str_starts_with($ceType, 'Profit') && str_starts_with($peType, 'Profit')) {
            return 'Both Profit';
        }

        if (str_starts_with($ceType, 'Panic')) {
            return 'Call Panic';
        }

        if (str_starts_with($peType, 'Panic')) {
            return 'Put Panic';
        }

        return 'Side';
    }

    private function dateRange(string $from, string $to): array
    {
       return  DB::table('nse_working_days')
                         ->whereBetween('working_date', [$from, $to])
                         ->orderBy('working_date')
                         ->pluck('working_date')
                         ->map(fn ($d) => (string) $d)
                         ->all();

    }
}
