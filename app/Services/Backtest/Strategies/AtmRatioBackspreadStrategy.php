<?php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AtmRatioBackspreadStrategy extends BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float $indexOpen,
        string $tradeDate,
        string $entryTimestamp,
        array $options
    ): ?array {
        $step = (int) ($options['step'] ?? 50);
        $baseQty = (int) ($options['lot'] ?? 65);

        $atm = (int) (round($indexOpen / 100) * 100);
        $entryCandle = Carbon::parse($entryTimestamp)->toDateTimeString();

        $atmCe = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'CE')
                   ->where('strike', $atm)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryCandle)
                   ->select('instrument_key', 'open', 'strike')
                   ->first();

        $atmPe = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'PE')
                   ->where('strike', $atm)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryCandle)
                   ->select('instrument_key', 'open', 'strike')
                   ->first();

        if (! $atmCe || ! $atmPe || (float) $atmCe->open <= 0 || (float) $atmPe->open <= 0) {
            \Log::info("ATM_RATIO_BACKSPREAD SKIP [{$tradeDate}] — ATM leg data missing at {$entryCandle}");
            return $this->skip('no_entry_candle');
        }

        $atmCePrice = (float) $atmCe->open;
        $atmPePrice = (float) $atmPe->open;

        $otmCe = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'CE')
                   ->where('strike', '>', $atm)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryCandle)
                   ->where('open', '>', 0)
                   ->orderBy('strike')
                   ->get(['instrument_key', 'open', 'strike'])
                   ->filter(fn ($row) => ((float) $row->open * 2) > $atmCePrice)
                   ->last();

        $otmPe = DB::table('expired_ohlc')
                   ->where('underlying_symbol', $symbol)
                   ->where('instrument_type', 'PE')
                   ->where('strike', '<', $atm)
                   ->where('interval', '5minute')
                   ->where('timestamp', $entryCandle)
                   ->where('open', '>', 0)
                   ->orderByDesc('strike')
                   ->get(['instrument_key', 'open', 'strike'])
                   ->filter(fn ($row) => ((float) $row->open * 2) > $atmPePrice)
                   ->last();

        if (! $otmCe) {
            \Log::info("ATM_RATIO_BACKSPREAD SKIP [{$tradeDate}] — no CE OTM strike where 2x premium > ATM CE premium");
            return $this->skip('no_ce_strike');
        }

        if (! $otmPe) {
            \Log::info("ATM_RATIO_BACKSPREAD SKIP [{$tradeDate}] — no PE OTM strike where 2x premium > ATM PE premium");
            return $this->skip('no_pe_strike');
        }

        return [
            [
                'strike' => $atm,
                'type' => 'CE',
                'side' => 'BUY',
                'role' => 'ATM_CE_BUY',
                'instrument_key' => $atmCe->instrument_key,
                'entry_price' => $atmCePrice,
                'exit_price' => null,
                'exit_time' => null,
                'exited' => false,
                'qty_override' => $baseQty,
                'entry_time' => $entryCandle,
            ],
            [
                'strike' => $atm,
                'type' => 'PE',
                'side' => 'BUY',
                'role' => 'ATM_PE_BUY',
                'instrument_key' => $atmPe->instrument_key,
                'entry_price' => $atmPePrice,
                'exit_price' => null,
                'exit_time' => null,
                'exited' => false,
                'qty_override' => $baseQty,
                'entry_time' => $entryCandle,
            ],
            [
                'strike' => (int) $otmCe->strike,
                'type' => 'CE',
                'side' => 'SELL',
                'role' => 'OTM_CE_SELL_X2',
                'instrument_key' => $otmCe->instrument_key,
                'entry_price' => (float) $otmCe->open,
                'exit_price' => null,
                'exit_time' => null,
                'exited' => false,
                'qty_override' => $baseQty * 2,
                'entry_time' => $entryCandle,
            ],
            [
                'strike' => (int) $otmPe->strike,
                'type' => 'PE',
                'side' => 'SELL',
                'role' => 'OTM_PE_SELL_X2',
                'instrument_key' => $otmPe->instrument_key,
                'entry_price' => (float) $otmPe->open,
                'exit_price' => null,
                'exit_time' => null,
                'exited' => false,
                'qty_override' => $baseQty * 2,
                'entry_time' => $entryCandle,
            ],
        ];
    }

    public function describe(array $options): string
    {
        $qty = (int) ($options['lot'] ?? 65);
        $step = (int) ($options['step'] ?? 50);

        return "ATM Ratio Backspread — buy 1x ATM CE/PE (nearest 100), sell 2x nearest OTM where 2× premium > ATM premium, base qty {$qty}, step {$step}";
    }
}
