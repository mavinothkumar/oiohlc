<?php

// app/Services/Backtest/Strategies/IronCondorLadderStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IronCondorLadderStrategy implements BacktestStrategy
{
    // Offsets from ATM to sell
    private const OFFSETS = [0, 100, 300];

    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        $qty = (int) ($options['lot'] ?? 65);

        // ATM = nearest 100
        $atm         = (int) (round($indexOpen / 100) * 100);
        $entryCandle = Carbon::parse($entryTs)->addMinutes(5)->toDateTimeString(); // 09:15 → 09:20

        Log::info("IRON_CONDOR_LADDER [{$tradeDate}] ATM={$atm} entry_candle={$entryCandle}");

        $legs = [];

        foreach (self::OFFSETS as $offset) {
            // CE side: ATM + offset
            // PE side: ATM - offset  (for offset=0 both are same strike)
            $strikes = $offset === 0
                ? [['strike' => $atm,          'type' => 'CE'],
                    ['strike' => $atm,          'type' => 'PE']]
                : [['strike' => $atm + $offset, 'type' => 'CE'],
                    ['strike' => $atm - $offset, 'type' => 'PE'],
                    ['strike' => $atm + $offset, 'type' => 'PE'],  // near straddle sells both types at same strike
                    ['strike' => $atm - $offset, 'type' => 'CE']];

            // Actually per your spec:
            // ATM       → sell CE + PE at 24000
            // ATM±100   → sell 24100 CE, 24100 PE, 23900 CE, 23900 PE
            // ATM±300   → sell 24300 CE, 24300 PE, 23700 CE, 23700 PE
            // So at each offset we sell BOTH CE and PE at BOTH upper and lower strikes
        }

        // ── Rebuild with correct leg structure ──────────────────────────────
        $legs = [];
        $legDefinitions = [];

        // ATM: CE + PE at same strike
        $legDefinitions[] = ['strike' => $atm,          'type' => 'CE'];
        $legDefinitions[] = ['strike' => $atm,          'type' => 'PE'];

        // ±100: CE+PE at upper, CE+PE at lower
        $legDefinitions[] = ['strike' => $atm + 100,    'type' => 'CE'];
        $legDefinitions[] = ['strike' => $atm + 100,    'type' => 'PE'];
        $legDefinitions[] = ['strike' => $atm - 100,    'type' => 'CE'];
        $legDefinitions[] = ['strike' => $atm - 100,    'type' => 'PE'];

        // ±300: CE+PE at upper, CE+PE at lower
        $legDefinitions[] = ['strike' => $atm + 300,    'type' => 'CE'];
        $legDefinitions[] = ['strike' => $atm + 300,    'type' => 'PE'];
        $legDefinitions[] = ['strike' => $atm - 300,    'type' => 'CE'];
        $legDefinitions[] = ['strike' => $atm - 300,    'type' => 'PE'];

        foreach ($legDefinitions as $def) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $def['type'])
                     ->where('strike', $def['strike'])
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryCandle)
                     ->select('instrument_key', 'open')
                     ->first();

            if (!$row || (float) $row->open <= 0) {
                Log::info("IRON_CONDOR_LADDER SKIP [{$tradeDate}] — No data for {$def['type']} {$def['strike']} at {$entryCandle}");
                return null;
            }

            $legs[] = [
                'strike'         => $def['strike'],
                'type'           => $def['type'],
                'instrument_key' => $row->instrument_key,
                'entry_price'    => (float) $row->open,
                'entry_time'     => $entryCandle,
                'exit_price'     => null,
                'exit_time'      => null,
                'exited'         => false,
                'qty_override'   => $qty,
            ];

            Log::info("IRON_CONDOR_LADDER [{$tradeDate}] {$def['type']} {$def['strike']} @ {$row->open}");
        }

        $totalPremium = array_sum(array_column($legs, 'entry_price'));
        Log::info("IRON_CONDOR_LADDER [{$tradeDate}] Total legs=" . count($legs) . " total_premium={$totalPremium}");

        return $legs;
    }

    public function describe(array $options): string
    {
        $qty = $options['lot'] ?? 65;
        return "Iron Condor Ladder — ATM + ±100 + ±300 CE+PE, {$qty} qty each (10 legs)";
    }
}
