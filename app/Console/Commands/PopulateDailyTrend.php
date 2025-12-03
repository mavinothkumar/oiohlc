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
        $targetDate = $this->option('date') ?: now()->subDay()->format('Y-m-d');

        $days = DB::table('nse_working_days')
                  ->where('working_date', $targetDate)
                  ->first();

        if ( ! $days) {
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
        // Same logic as original controller for yesterday's static data
        $indexRow = DailyOhlcQuote::where('option_type', 'INDEX')
                                  ->where('quote_date', $quoteDate)
                                  ->where('symbol_name', $symbol)
                                  ->first();

        if ( ! $indexRow) {
            return null;
        }

        $currentExpiry = DailyOhlcQuote::where('quote_date', $quoteDate)
                                       ->where('symbol_name', $symbol)
                                       ->whereIn('option_type', ['CE', 'PE'])
                                       ->orderBy('expiry_date')
                                       ->value('expiry_date');

        $options = DailyOhlcQuote::where('quote_date', $quoteDate)
                                 ->where('symbol_name', $symbol)
                                 ->whereIn('option_type', ['CE', 'PE'])
                                 ->when($currentExpiry, fn($q) => $q->where('expiry_date', $currentExpiry))
                                 ->get();

        if ($options->isEmpty()) {
            return null;
        }

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

        $earthValue = ($indexRow->high - $indexRow->low) * 0.2611;

        // ---- Type logic per side (same as controller) ----
        $ceType = $this->computeType($ce, $symbol);
        $peType = $this->computeType($pe, $symbol);

        // Optional aggregate market_type (e.g. CE/PE combination)
        $marketType = $this->computeMarketType($ceType, $peType);

        return [
            'quote_date'  => $quoteDate,
            'symbol_name' => $symbol,
            'index_high'  => $indexRow->high,
            'index_low'   => $indexRow->low,
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
            $type      .= ' Side';
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
