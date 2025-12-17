<?php

namespace App\Console\Commands;

use App\Models\StraddleEntry;
use App\Models\StraddleEntrySlot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BacktestStraddle extends Command
{
    protected $signature = 'backtest:straddle
                            {expiry : Expiry date in Y-m-d format}
                            {--symbol=NIFTY}';

    protected $description = 'Run intraday straddle backtest (hourly) for all trade days of a given expiry';

    public function handle(): int
    {
        $symbol = $this->option('symbol');
        $expiry = $this->argument('expiry');

        $this->info("Starting straddle backtest for {$symbol} {$expiry}");

        // All 5-min trading dates for this expiry
        $dates = DB::table('expired_ohlc')
                   ->selectRaw('DATE(`timestamp`) as trade_date')
                   ->where('underlying_symbol', $symbol)
                   ->where('expiry', $expiry)
                   ->where('interval', '5minute')
                   ->distinct()
                   ->orderBy('trade_date')
                   ->pluck('trade_date');

        if ($dates->isEmpty()) {
            $this->warn("No 5min data found for {$symbol} {$expiry}");
            return Command::SUCCESS;
        }

        foreach ($dates as $tradeDate) {
            $this->processTradeDate($symbol, $expiry, $tradeDate);
        }

        $this->info('Straddle backtest completed.');
        return Command::SUCCESS;
    }

    protected function processTradeDate(string $symbol, string $expiry, string $tradeDate): void
    {
        $this->info("Processing {$tradeDate} for {$symbol} {$expiry}");

        /**
         * STEP 1: 09:20 index close
         */
        $index920 = DB::table('expired_ohlc')
                      ->where('underlying_symbol', $symbol)
                      ->where('instrument_type', 'INDEX')   // adjust if your index type differs
                      ->where('interval', '5minute')
                      ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                      ->whereTime('timestamp', '09:20:00')
                      ->orderBy('timestamp')
                      ->first();

        if (!$index920) {
            $this->warn("  Skipped {$tradeDate}: no 09:20 index candle.");
            return;
        }

        $index920Close = (float) $index920->close;


        /**
         * STEP 2: find ATM using CE+PE sum at 09:20
         *         We pick the strike whose CE+PE close sum is closest to index close at 09:20.
         */
        $options920 = DB::table('expired_ohlc')
                        ->select('strike', 'instrument_type', 'close', 'instrument_key')
                        ->where('underlying_symbol', $symbol)
                        ->whereIn('instrument_type', ['CE', 'PE'])
                        ->where('interval', '5minute')
                        ->where('expiry', $expiry)
                        ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                        ->whereTime('timestamp', '09:20:00')
                        ->orderBy('strike')
                        ->get();



        if ($options920->isEmpty()) {
            $this->warn("  Skipped {$tradeDate}: no CE/PE at 09:20.");
            return;
        }

        $grouped = $options920->groupBy('strike');



        $bestStrike      = null;
        $bestDiffFromIdx = null;
        $ceKeyAtBest     = null;
        $peKeyAtBest     = null;

        foreach ($grouped as $strike => $rows) {
            $ce = $rows->firstWhere('instrument_type', 'CE');
            $pe = $rows->firstWhere('instrument_type', 'PE');

            if (!$ce || !$pe) {
                continue; // need both legs
            }

//            $sumPremium = (float) $ce->close + (float) $pe->close;
//            $diff       = abs($sumPremium - $index920Close);
            $diff       =  abs((float) $ce->close - (float) $pe->close);
            //$this->info("Processing {$strike} for {$sumPremium} - {$diff}");

            if ($bestDiffFromIdx === null || $diff < $bestDiffFromIdx) {
                $bestDiffFromIdx = $diff;
                $bestStrike      = $strike;
                $ceKeyAtBest     = $ce->instrument_key ?? null;
                $peKeyAtBest     = $pe->instrument_key ?? null;
            }
           // $this->info("Processing {$strike} best {$bestStrike} - {$bestDiffFromIdx}");
        }
        if ($bestStrike === null) {
            $this->warn("  Skipped {$tradeDate}: no valid CE+PE pair at 09:20.");
            return;
        }

        $atmStrike = (int) $bestStrike;

        $this->info("  ATM strike on {$tradeDate} from CE+PE at 09:20 = {$atmStrike} (index {$index920Close})");

        /**
         * STEP 3: 09:20 CE / PE OPEN at ATM strike (entry prices)
         *         We use open of the 09:20 candle for the selected strike.
         */
        $entryTime = Carbon::parse($tradeDate . ' 09:20:00');

        $ce920 = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'CE')
                   ->where('interval', '5minute')
                   ->where('expiry', $expiry)
                   ->where('strike', $atmStrike)
                   ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                   ->whereTime('timestamp', '09:20:00')
                   ->orderBy('timestamp')
                   ->first();

        $pe920 = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'PE')
                   ->where('interval', '5minute')
                   ->where('expiry', $expiry)
                   ->where('strike', $atmStrike)
                   ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                   ->whereTime('timestamp', '09:20:00')
                   ->orderBy('timestamp')
                   ->first();

        if (!$ce920 || !$pe920) {
            $this->warn("  Skipped {$tradeDate}: missing CE/PE 09:20 candles at ATM strike {$atmStrike}.");
            return;
        }

        $ceEntry = (float) $ce920->open;
        $peEntry = (float) $pe920->open;

        /**
         * STEP 4: store / reuse StraddleEntry (per trade date + expiry + ATM)
         */
        $entry = StraddleEntry::firstOrCreate(
            [
                'symbol'      => $symbol,
                'expiry_date' => $expiry,
                'trade_date'  => $tradeDate,
                'atm_strike'  => $atmStrike,
                'entry_time'  => $entryTime,
            ],
            [
                'index_at_entry' => $index920Close,
                'ce_symbol'      => $ceKeyAtBest ?? $ce920->instrument_key ?? null,
                'pe_symbol'      => $peKeyAtBest ?? $pe920->instrument_key ?? null,
                'ce_strike'      => $atmStrike,
                'pe_strike'      => $atmStrike,
                'ce_entry_price' => $ceEntry,
                'pe_entry_price' => $peEntry,
            ]
        );

        $this->info("  Entry saved: trade_date {$tradeDate}, ATM {$atmStrike}");

        /**
         * STEP 5: hourly slots (can include 09_30, 10_00, 10_30, etc. if you expanded)
         */
        $slots = [
            '09_30' => '09:30:00',
            '10_00' => '10:00:00',
            '10_30' => '10:30:00',
            '11_00' => '11:00:00',
            '11_30' => '11:30:00',
            '12_00' => '12:00:00',
            '12_30' => '12:30:00',
            '13_00' => '13:00:00',
            '13_30' => '13:30:00',
            '14_00' => '14:00:00',
            '14_30' => '14:30:00',
            '15_00' => '15:00:00',
        ];

        foreach ($slots as $hourSlot => $timeString) {
            $slotTime = Carbon::parse($tradeDate . ' ' . $timeString);

            // Skip if this slot already exists (no override)
            $existing = StraddleEntrySlot::where('symbol', $symbol)
                                         ->whereDate('expiry_date', $expiry)
                                         ->whereDate('trade_date', $tradeDate)
                                         ->where('atm_strike', $atmStrike)
                                         ->where('hour_slot', $hourSlot)
                                         ->where('slot_time', $slotTime)
                                         ->first();

            if ($existing) {
                $this->line("    Slot {$hourSlot} ({$timeString}) already exists, skipping.");
                continue;
            }

            $ceSlot = DB::table('expired_ohlc')
                        ->where('underlying_symbol', $symbol)
                        ->where('instrument_type', 'CE')
                        ->where('interval', '5minute')
                        ->where('expiry', $expiry)
                        ->where('strike', $atmStrike)
                        ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                        ->whereTime('timestamp', $timeString)
                        ->orderBy('timestamp')
                        ->first();

            $peSlot = DB::table('expired_ohlc')
                        ->where('underlying_symbol', $symbol)
                        ->where('instrument_type', 'PE')
                        ->where('interval', '5minute')
                        ->where('expiry', $expiry)
                        ->where('strike', $atmStrike)
                        ->whereRaw('DATE(`timestamp`) = ?', [$tradeDate])
                        ->whereTime('timestamp', $timeString)
                        ->orderBy('timestamp')
                        ->first();

            if (!$ceSlot || !$peSlot) {
                $this->warn("    Missing CE/PE candles for slot {$hourSlot} {$tradeDate} {$timeString}, skipping.");
                continue;
            }

            $ceClose = (float) $ceSlot->close;
            $peClose = (float) $peSlot->close;

            // Short straddle P&L
            $cePnl = $ceEntry - $ceClose;
            $pePnl = $peEntry - $peClose;
            $totalPnl = $cePnl + $pePnl;

            StraddleEntrySlot::create([
                'symbol'          => $symbol,
                'expiry_date'     => $expiry,
                'trade_date'      => $tradeDate,
                'hour_slot'       => $hourSlot,
                'slot_time'       => $slotTime,
                'atm_strike'      => $atmStrike,
                'ce_strike'       => $atmStrike,
                'pe_strike'       => $atmStrike,
                'ce_entry_price'  => $ceEntry,
                'pe_entry_price'  => $peEntry,
                'ce_close_price'  => $ceClose,
                'pe_close_price'  => $peClose,
                'ce_pnl'          => $cePnl,
                'pe_pnl'          => $pePnl,
                'total_pnl'       => $totalPnl,
            ]);

            $this->line("    Created slot {$hourSlot} ({$timeString}) with total PnL {$totalPnl}");
        }
    }
}
