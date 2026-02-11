<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DailyTrend;
use App\Models\DailyOhlcQuote;
use Illuminate\Support\Facades\DB;

class PopulateDailyTrend extends Command
{
    protected $signature = 'trend:populate-daily {--date= : Specific date YYYY-MM-DD}';
    protected $description = 'Populate daily trend data from yesterday\'s OHLC';

    public function handle()
    {

        $targetDate = DB::table('nse_working_days')
                        ->where('previous', 1)
                        ->value('working_date');

        if ( ! $targetDate) {
            $this->error("No working day found for {$targetDate}");

            return 1;
        }

        $symbols = ['NIFTY', 'BANKNIFTY', 'SENSEX']; //, 'FINNIFTY'
        $saved   = 0;

        foreach ($symbols as $symbol) {
            $data = $this->calculateTrendData($targetDate, $symbol);
            if ($data) {
                DailyTrend::updateOrCreate(
                    ['quote_date' => $targetDate, 'symbol_name' => $symbol],
                    $data
                );
                $saved++;
            }
        }

        $this->info("Saved/updated {$saved} records for {$targetDate}");

        return 0;
    }

    private function calculateTrendData($quoteDate, $symbol)
    {
        $buildExpiredDailyTrend = new BuildExpiredDailyTrend();
        // Same logic as original controller for yesterday's static data
        $indexRow = DailyOhlcQuote::where('option_type', 'INDEX')
                                  ->where('quote_date', $quoteDate)
                                  ->where('symbol_name', $symbol)
                                  ->first();

        if ( ! $indexRow) {
            return null;
        }

//        $currentExpiry = DailyOhlcQuote::where('quote_date', $quoteDate)
//                                       ->where('symbol_name', $symbol)
//                                       ->whereIn('option_type', ['CE', 'PE'])
//                                       ->orderBy('expiry_date')
//                                       ->value('expiry_date');

        $currentExpiry = DB::table('nse_expiries')
                           ->where('is_current', 1)
                           ->where('instrument_type', 'OPT')
                           ->where('trading_symbol', $symbol)
                           ->value('expiry_date');

        $options = DailyOhlcQuote::where('quote_date', $quoteDate)
                                 ->where('symbol_name', $symbol)
                                 ->whereIn('option_type', ['CE', 'PE'])
                                 ->when($currentExpiry, fn($q) => $q->where('expiry_date', $currentExpiry))
                                 ->get();

        if ($options->isEmpty()) {
            return null;
        }

        $bestPair = $buildExpiredDailyTrend->findBestPair($options, $symbol, true);
        if ( ! $bestPair) {
            return null;
        }

        $strike   = $bestPair['strike'];
        $ce       = $bestPair['ce'];
        $pe       = $bestPair['pe'];
        $ceClose  = $ce->close;
        $peClose  = $pe->close;
        $sumClose = $ceClose + $peClose;


        $atmData      = $buildExpiredDailyTrend->findNearestAtmPair($options, $strike, $symbol, true);
        $atm_ce       = $atmData['atm_ce'];
        $atm_pe       = $atmData['atm_pe'];
        $atm_ce_close = $atmData['atm_ce_close'];
        $atm_pe_close = $atmData['atm_pe_close'];
        $atm_ce_high  = $atmData['atm_ce_high'];
        $atm_pe_high  = $atmData['atm_pe_high'];
        $atm_ce_low   = $atmData['atm_ce_low'];
        $atm_pe_low   = $atmData['atm_pe_low'];

        $earthValue = ($indexRow->high - $indexRow->low) * 0.2611;

        // ---- Type logic per side (same as controller) ----
        $ceType = $buildExpiredDailyTrend->computeType($ce, $symbol);
        $peType = $buildExpiredDailyTrend->computeType($pe, $symbol);

        // Optional aggregate market_type (e.g. CE/PE combination)
        $marketType = $buildExpiredDailyTrend->computeMarketType($ceType, $peType);

        $midPoint = $sumClose/2;

        return [
            'quote_date'  => $quoteDate,
            'symbol_name' => $symbol,
            'index_high'  => $indexRow->high,
            'index_low'   => $indexRow->low,
            'index_close' => $indexRow->close,
            'earth_value' => $earthValue,
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
            'expiry_date' => $currentExpiry,
            'market_type' => $marketType,
            'ce_type'     => $ceType,
            'pe_type'     => $peType,

            'mid_point'    => $midPoint,

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

    private function findBestPair($options)
    {
        $groupedByStrike = $options->groupBy('strike');
        $bestPair        = null;
        $bestDiff        = null;

        foreach ($groupedByStrike as $strike => $contracts) {
            $ce = $contracts->firstWhere('option_type', 'CE');
            $pe = $contracts->firstWhere('option_type', 'PE');

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
        // You can define your own combination rules; simple example:
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

}
