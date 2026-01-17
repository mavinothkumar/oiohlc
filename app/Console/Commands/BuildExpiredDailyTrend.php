<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DailyTrend;

class BuildExpiredDailyTrend extends Command
{
    public $signature = 'trend:build
                            {--from= : Backtest start date YYYY-MM-DD}
                            {--to= : Backtest end date YYYY-MM-DD}';

    public $description = 'Populate / backfill daily_trend and compute earth levels from live or expired OHLC';

    public $expiry_days;
    public $from;
    public $to;
    public $current_date;
    public $currentExpiry;
    public $previous_date;
    public $openPrice;

    public function handle(): int
    {
        return $this->runBacktest();
    }

    /* ============================================================
     * BACKTEST MODE (using expired_ohlc only)
     * ============================================================
     */
    public function runBacktest(): int
    {
        $this->from        = $this->option('from');
        $this->to          = $this->option('to') ?: $this->from;
        $this->expiry_days = DB::table('expired_expiries')->whereBetween('expiry_date', [$this->from, $this->to])->pluck('expiry_date')->toArray();

        if ( ! $this->from) {
            $this->error('For backtest, please provide --from=YYYY-MM-DD (and optionally --to=YYYY-MM-DD)');

            return 1;
        }

        $workingDays = $this->dateRange($this->from, $this->to);

        if (empty($workingDays)) {
            $this->warn("No working days found between {$this->from} and {$this->to} in nse_working_days.");

            return 0;
        }

        $symbols = ['NIFTY'];//, 'BANKNIFTY', 'SENSEX'

        foreach ($workingDays as $d) {
            $saved              = 0;
            $this->current_date = $d;
            foreach ($symbols as $symbol) {

                $this->previous_date = $this->getPreviousWorkingDate();

                if ( ! $this->previous_date) {
                    $this->warn("No previous working day for {$this->current_date}");
                    continue;
                }
                $this->openPrice = $this->getBacktestOpenFromExpired($symbol);

                // Build levels from previous working day data
                $data = $this->buildFromPreviousDay($symbol);
                if ( ! $data) {
                    $this->warn("Returning from buildFromPreviousDay $this->previous_date :".$this->previous_date);
                    continue;
                }

                // Now attach today's open and earth bands

//                if ($this->openPrice !== null) {
//                    $earthValue                     = (float) $data['earth_value'];
//                    $data['current_day_index_open'] = $this->openPrice;
//                    $data['earth_high']             = $this->openPrice + $earthValue;
//                    $data['earth_low']              = $this->openPrice - $earthValue;
//                }

                // VERY IMPORTANT: quote_date is today (D), NOT prevDate
                $data['quote_date']  = $this->current_date;
                $data['symbol_name'] = $symbol;

                DailyTrend::updateOrCreate(
                    ['quote_date' => $this->current_date, 'symbol_name' => $symbol],
                    $data
                );

                $saved++;
            }

            $this->info("BACKTEST mode: Saved/updated {$saved} records for working date {$this->current_date} and expiry {$this->currentExpiry}");
        }

        return 0;
    }

    public function getCurrentWeekExpiry(string $symbol): ?string
    {
        $condition = '>=';
        if (in_array($this->previous_date, $this->expiry_days)) {
            $condition = '>';
        }

//        $this->info('$this->current_date ' . $this->current_date);
//        $this->info('$this->previous_date ' . $this->previous_date);
//        $this->info(DB::table('expired_expiries')
//                      ->where('underlying_symbol', $symbol)
//                      ->where('instrument_type', 'OPT')
//                      ->whereDate('expiry_date', $condition, $this->current_date)
//                      ->orderBy('expiry_date')->toRawSql());
        //exit;

        return DB::table('expired_expiries')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', 'OPT')
                 ->whereDate('expiry_date', $condition, $this->current_date)
                 ->orderBy('expiry_date')
                 ->value('expiry_date');
    }

    public function buildFromPreviousDay(string $symbol): ?array
    {
        // 1) previous day index OHLC (daily candle)
        $indexRow = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->where('instrument_type', 'INDEX')
                      ->where('interval', 'day') // adjust if needed
                      ->whereDate('timestamp', $this->previous_date)
                      ->first();

        if ( ! $indexRow) {
            $this->warn('No $indexRow');

            return null;
        }

        // 2) current week expiry as on prevDate
        $this->currentExpiry = $this->getCurrentWeekExpiry($symbol);


        if ( ! $this->currentExpiry) {
            $this->warn('$No $this->currentExpiry');

            return null;
        }


        // 3) previous day options for that fixed expiry
        $options = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->whereIn('instrument_type', ['CE', 'PE'])
                     ->where('interval', 'day')
                     ->whereDate('timestamp', $this->previous_date)
                     ->whereDate('expiry', $this->currentExpiry)
                     ->get();

        if ($options->isEmpty()) {
            $this->info(DB::table('expired_ohlc')
                          ->where('underlying_symbol', $symbol)
                          ->whereIn('instrument_type', ['CE', 'PE'])
                          ->where('interval', 'day')
                          ->whereDate('timestamp', $this->previous_date)
                          ->whereDate('expiry', $this->currentExpiry)->toRawSql());
            $this->warn('$options is empty');

            return null;
        }

        return $this->buildTrendFromOhlc(
            $indexRow->high,
            $indexRow->low,
            $indexRow->close,
            $options,
            $symbol,
            $this->currentExpiry
        );
    }

    public function buildTrendFromOhlc(
        float $indexHigh,
        float $indexLow,
        float $indexClose,
        $options,
        string $symbol,
        $expiryDate
    ): ?array {
        $bestPair = $this->findBestPair($options);
        if ( ! $bestPair) {
            return null;
        }

        $strike   = $bestPair['strike'];
        $ce       = $bestPair['ce'];
        $pe       = $bestPair['pe'];
        $ceClose  = $ce->close;
        $peClose  = $pe->close;
        $sumClose = $ceClose + $peClose;

        // New ATM logic
        $atmData      = $this->findNearestAtmPair($options, $strike);
        $atm_ce       = $atmData['atm_ce'];
        $atm_pe       = $atmData['atm_pe'];
        $atm_ce_close = $atmData['atm_ce_close'];
        $atm_pe_close = $atmData['atm_pe_close'];
        $atm_ce_high  = $atmData['atm_ce_high'];
        $atm_pe_high  = $atmData['atm_pe_high'];
        $atm_ce_low   = $atmData['atm_ce_low'];
        $atm_pe_low   = $atmData['atm_pe_low'];

        $earthValue = ($indexHigh - $indexLow) * 0.2611;

        $ceType     = $this->computeType($ce, $symbol);
        $peType     = $this->computeType($pe, $symbol);
        $marketType = $this->computeMarketType($ceType, $peType);

        $data = [
            'index_high'  => $indexHigh,
            'index_low'   => $indexLow,
            'index_close' => $indexClose,
            'earth_value' => $earthValue,

            'strike'   => $strike,
            'ce_high'  => $ce->high,
            'ce_low'   => $ce->low,
            'ce_close' => $ceClose,
            'pe_high'  => $pe->high,
            'pe_low'   => $pe->low,
            'pe_close' => $peClose,
            'min_r'    => $strike + $ceClose,
            'min_s'    => $strike - $peClose,
            'max_r'    => $strike + $sumClose,
            'max_s'    => $strike - $sumClose,

            'expiry_date'  => $expiryDate,
            'market_type'  => $marketType,
            'ce_type'      => $ceType,
            'pe_type'      => $peType,

            // New ATM fields
            'atm_ce'       => $atm_ce,
            'atm_pe'       => $atm_pe,
            'atm_ce_close' => $atm_ce_close,
            'atm_pe_close' => $atm_pe_close,
            'atm_ce_high'  => $atm_ce_high,
            'atm_pe_high'  => $atm_pe_high,
            'atm_ce_low'   => $atm_ce_low,
            'atm_pe_low'   => $atm_pe_low,
        ];

        // Attach today's open and compute ATM levels (only if open price available)
        if (isset($this->openPrice) && $this->openPrice !== null) {  // $openPrice from runBacktest()
            $data['current_day_index_open'] = $this->openPrice;
            $data['earth_high']             = $this->openPrice + $earthValue;
            $data['earth_low']              = $this->openPrice - $earthValue;

            // Round to nearest 50 for NIFTY
            $atm_index_open         = round($this->openPrice / 50) * 50;
            $data['atm_index_open'] = $atm_index_open;

            // ATM R/S levels
            $data['atm_r_avg'] = $atm_index_open + (($atm_ce_close + $atm_pe_close) / 2);
            $data['atm_s_avg'] = $atm_index_open - (($atm_ce_close + $atm_pe_close) / 2);

            $data['atm_r'] = $atm_index_open + ($atm_ce_close / 2);
            $data['atm_s'] = $atm_index_open - ($atm_pe_close / 2);

            $data['atm_r_1'] = $atm_index_open + $atm_ce_close;
            $data['atm_s_1'] = $atm_index_open - $atm_pe_close;

            $data['atm_r_2'] = $atm_index_open + $atm_ce_close + ($atm_ce_close / 2);
            $data['atm_s_2'] = $atm_index_open - $atm_pe_close - ($atm_pe_close / 2);

            $data['atm_r_3'] = $atm_index_open + $atm_ce_close + $atm_ce_close;
            $data['atm_s_3'] = $atm_index_open - $atm_pe_close - $atm_pe_close;

            $data['open_type']  = $this->buildOpenType($indexClose, $indexHigh, $indexLow, $this->openPrice);
            $data['open_value'] = $this->openPrice - $indexClose;

        }

        return $data;
    }


    public function getBacktestOpenFromExpired(string $symbol): ?float
    {
        // Option 1: daily open
        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', 'INDEX')
                 ->where('interval', 'day')
                 ->whereDate('timestamp', $this->current_date)
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
                  ->whereDate('timestamp', $this->current_date)
                  ->orderBy('timestamp')
                  ->first();

        return $row5 ? (float) $row5->open : null;
    }

    public function getPreviousWorkingDate(): ?string
    {
        return DB::table('nse_working_days')
                 ->where('working_date', '<', $this->current_date)
                 ->orderByDesc('working_date')
                 ->value('working_date'); // returns string date or null
    }

    public function findBestPair($options, $symbol = 'NIFTY', $live = false): ?array
    {
        $symbol_name     = $live ? 'symbol_name' : 'underlying_symbol';
        $groupedByStrike = collect($options)->where($symbol_name, $symbol)->groupBy('strike');
        $bestPair        = null;
        $bestDiff        = null;

        foreach ($groupedByStrike as $strike => $contracts) {
            $ce = $contracts->firstWhere('instrument_type', 'CE') ?? $contracts->firstWhere('option_type', 'CE');
            $pe = $contracts->firstWhere('instrument_type', 'PE') ?? $contracts->firstWhere('option_type', 'PE');

            if ( ! $ce || ! $pe) {
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

    public function computeType($contract, string $symbol): string
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

    public function computeMarketType(string $ceType, string $peType): string
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

    public function dateRange(string $from, string $to): array
    {
        return DB::table('nse_working_days')
                 ->whereBetween('working_date', [$from, $to])
                 ->orderBy('working_date')
                 ->pluck('working_date')
                 ->map(fn($d) => (string) $d)
                 ->all();

    }

    /**
     * Find ATM CE strike and nearest PE strike based on CE close price
     */
    public function findNearestAtmPair($options, $strike, $symbol = 'NIFTY', $live = false): array
    {
        $symbol_name = $live ? 'symbol_name' : 'underlying_symbol';
        // Group options by strike - value is Collection of contracts at that strike
        $groupedByStrike = collect($options)->where($symbol_name, $symbol)->groupBy('strike');

        // Find ATM CE contract (use first available CE as ATM CE)
        $atmCe = null;
        foreach ($groupedByStrike as $contracts) {
            foreach ($contracts as $contract) {
                $contract_type = $contract->instrument_type ?? $contract->option_type;
                if ($contract_type === 'CE' && (int) $contract->strike === $strike) {
                    $atmCe = $contract;
                    break 2; // Break both loops
                }
            }
        }

        if ( ! $atmCe) {
            return [
                'atm_ce'      => 0, 'atm_pe' => 0, 'atm_ce_close' => 0, 'atm_pe_close' => 0,
                'atm_ce_high' => 0, 'atm_pe_high' => 0, 'atm_ce_low' => 0, 'atm_pe_low' => 0,
            ];
        }

        $atm_ce       = (float) $atmCe->strike;
        $atm_ce_close = (float) $atmCe->close;
        $atm_ce_high  = (float) $atmCe->high;
        $atm_ce_low   = (float) $atmCe->low;

        // Find PE with closest close price to ATM CE close
        $bestPeDiff    = null;
        $atmPeContract = null;

        foreach ($groupedByStrike as $contracts) {
            foreach ($contracts as $contract) {
                $contract_type = $contract->instrument_type ?? $contract->option_type;
                if ($contract_type === 'PE') {
                    $diff = abs((float) $contract->close - $atm_ce_close);
                    if ($bestPeDiff === null || $diff < $bestPeDiff) {
                        $bestPeDiff    = $diff;
                        $atmPeContract = $contract;
                    }
                }
            }
        }

        $atm_pe       = $atmPeContract ? (float) $atmPeContract->strike : $atm_ce;
        $atm_pe_close = $atmPeContract ? (float) $atmPeContract->close : 0;
        $atm_pe_high  = $atmPeContract ? (float) $atmPeContract->high : 0;
        $atm_pe_low   = $atmPeContract ? (float) $atmPeContract->low : 0;

        return [
            'atm_ce'       => $atm_ce,
            'atm_pe'       => $atm_pe,
            'atm_ce_close' => $atm_ce_close,
            'atm_pe_close' => $atm_pe_close,
            'atm_ce_high'  => $atm_ce_high,
            'atm_pe_high'  => $atm_pe_high,
            'atm_ce_low'   => $atm_ce_low,
            'atm_pe_low'   => $atm_pe_low,
        ];
    }

    /**
     * Determine option type based on open vs previous day OHLC
     */
    public function buildOpenType(float $indexClose, float $indexHigh, float $indexLow, float $openPrice): string|null
    {
        if ($openPrice === null) {
            return 'Unknown';
        }

        if ($openPrice > $indexHigh) {
            return 'Gap Up';
        } elseif ($openPrice < $indexLow) {
            return 'Gap Down';
        } elseif ($openPrice > $indexClose && $openPrice <= $indexHigh) {
            return 'Positive Open';
        } elseif ($openPrice < $indexClose && $openPrice >= $indexLow) {
            return 'Negative Open';
        }

        return null;
    }


}
