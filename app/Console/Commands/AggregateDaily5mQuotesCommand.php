<?php

namespace App\Console\Commands;

use App\Services\NiftyConstituentManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateDaily5mQuotesCommand extends Command
{
    protected $signature = 'ohlc:aggregate-daily-5m {--date= : The date to aggregate (default: today)}';
    protected $description = 'Aggregate 1-minute live quotes from ohlc_quotes/ohlc_quotes_history into ohlc_5m_quotes for Nifty 50 and constituent stocks';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $date = $this->option('date') ?: $nowKolkata->toDateString();

        $this->info("Aggregating 1-minute quotes into 5-minute candles for {$date}...");

        $periodInfo = NiftyConstituentManager::getConstituentsForDate($date);
        $symbols = array_column($periodInfo['constituents'], 'symbol');
        $symbols[] = 'Nifty 50';
        $symbols[] = 'NIFTY';

        // Check ohlc_quotes first (during market), otherwise ohlc_quotes_history (after 15:40 archive)
        $table = 'ohlc_quotes';
        $hasQuotes = DB::table($table)
            ->whereIn('trading_symbol', $symbols)
            ->where('ts_at', '>=', "{$date} 09:15:00")
            ->where('ts_at', '<=', "{$date} 15:30:00")
            ->exists();

        if (!$hasQuotes) {
            $table = 'ohlc_quotes_history';
        }

        $quotes = DB::table($table)
            ->whereIn('trading_symbol', $symbols)
            ->where('ts_at', '>=', "{$date} 09:15:00")
            ->where('ts_at', '<=', "{$date} 15:30:00")
            ->orderBy('ts_at')
            ->get();

        if ($quotes->isEmpty()) {
            $this->warn("No 1-minute quotes found in {$table} for {$date}.");
            return self::SUCCESS;
        }

        $byInst = $quotes->groupBy('instrument_key');
        $candlesToInsert = [];
        $now = now();

        foreach ($byInst as $instKey => $instQuotes) {
            $first = $instQuotes->first();
            $sym = $first->trading_symbol;
            $type = $first->instrument_type;

            $buckets = [];
            foreach ($instQuotes as $q) {
                $ts = Carbon::parse($q->ts_at, 'Asia/Kolkata');
                $min = (int)$ts->format('i');
                $bMin = $min - ($min % 5);
                $bTime = $ts->format('H') . ':' . str_pad($bMin, 2, '0', STR_PAD_LEFT);
                $buckets[$bTime][] = $q;
            }

            foreach ($buckets as $bTime => $group) {
                $f = $group[0];
                $l = end($group);
                $open = (float)$f->open;
                $close = (float)$l->close;
                $high = (float)collect($group)->max('high');
                $low = (float)collect($group)->min('low');
                $vol = (int)collect($group)->sum('volume');
                $tsAt = "{$date} {$bTime}:00";
                $ts = Carbon::parse($tsAt, 'Asia/Kolkata')->timestamp;

                $candlesToInsert[] = [
                    'instrument_key' => $instKey,
                    'instrument_type' => $type,
                    'trading_symbol' => $sym,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close,
                    'volume' => $vol,
                    'ts' => $ts,
                    'ts_at' => $tsAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Clean existing for this date in ohlc_5m_quotes
        DB::table('ohlc_5m_quotes')
            ->whereIn('trading_symbol', $symbols)
            ->where('ts_at', '>=', "{$date} 00:00:00")
            ->where('ts_at', '<=', "{$date} 23:59:59")
            ->delete();

        foreach (array_chunk($candlesToInsert, 500) as $chunk) {
            DB::table('ohlc_5m_quotes')->insert($chunk);
        }

        $this->info("Successfully aggregated " . count($candlesToInsert) . " 5-minute candles into ohlc_5m_quotes from {$table}.");

        return self::SUCCESS;
    }
}
