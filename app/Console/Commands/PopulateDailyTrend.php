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

        if (!$days) {
            $this->error("No working day found for {$targetDate}");
            return 1;
        }

        $symbols = ['NIFTY', 'BANKNIFTY', 'SENSEX']; //, 'FINNIFTY'
        $saved = 0;

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

        if (!$indexRow) return null;

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

        if ($options->isEmpty()) return null;

        $bestPair = $this->findBestPair($options);
        if (!$bestPair) return null;

        $earthValue = ($indexRow->high - $indexRow->low) * 0.2611;

        return [
            'quote_date' => $quoteDate,
            'symbol_name' => $symbol,
            'index_high' => $indexRow->high,
            'index_low' => $indexRow->low,
            'earth_value' => $earthValue,
            'strike' => $bestPair['strike'],
            'ce_high' => $bestPair['ce']->high,
            'ce_low' => $bestPair['ce']->low,
            'ce_close' => $bestPair['ce']->close,
            'pe_high' => $bestPair['pe']->high,
            'pe_low' => $bestPair['pe']->low,
            'pe_close' => $bestPair['pe']->close,
            'min_r' => $bestPair['strike'] + $bestPair['ce']->close,
            'min_s' => $bestPair['strike'] - $bestPair['pe']->close,
            'max_r' => $bestPair['strike'] + $bestPair['ce']->close + $bestPair['pe']->close,
            'max_s' => $bestPair['strike'] - $bestPair['ce']->close - $bestPair['pe']->close,
            'expiry_date' => $currentExpiry,
        ];
    }

    private function findBestPair($options)
    {
        $groupedByStrike = $options->groupBy('strike');
        $bestPair = null;
        $bestDiff = null;

        foreach ($groupedByStrike as $strike => $contracts) {
            $ce = $contracts->firstWhere('option_type', 'CE');
            $pe = $contracts->firstWhere('option_type', 'PE');

            if (!$ce || !$pe) continue;

            $diff = abs($ce->close - $pe->close);
            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff = $diff;
                $bestPair = [
                    'strike' => $strike,
                    'ce' => $ce,
                    'pe' => $pe,
                ];
            }
        }

        return $bestPair;
    }
}
