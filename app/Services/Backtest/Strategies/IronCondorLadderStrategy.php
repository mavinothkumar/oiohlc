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
        $qty         = (int) ($options['lot'] ?? 65);
        $atm         = (int) (round($indexOpen / 100) * 100);
        $entryCandle = Carbon::parse($entryTs)->addMinutes(5)->toDateTimeString();

        $legDefinitions = [
            ['strike' => $atm,        'type' => 'CE'],
            ['strike' => $atm,        'type' => 'PE'],
            ['strike' => $atm + 100,  'type' => 'CE'],
            ['strike' => $atm + 100,  'type' => 'PE'],
            ['strike' => $atm - 100,  'type' => 'CE'],
            ['strike' => $atm - 100,  'type' => 'PE'],
            ['strike' => $atm + 300,  'type' => 'CE'],
            ['strike' => $atm + 300,  'type' => 'PE'],
            ['strike' => $atm - 300,  'type' => 'CE'],
            ['strike' => $atm - 300,  'type' => 'PE'],
        ];

        $legs = [];
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
                Log::info("ICL SKIP [{$tradeDate}] — No data for {$def['type']} {$def['strike']}");
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
        }

        // ── Dynamic target based on total premium ──────────────────────
        // Sum of all 10 leg open prices = total premium collected in points
        $totalPremiumPts = array_sum(array_column($legs, 'entry_price'));

        // Tiers (tune these thresholds after running backtests):
        // Low IV   < 200 pts  → ₹13,000 target (collect what you can)
        // Normal   200–270    → ₹14,000 target (standard)
        // High IV  270–350    → ₹16,000 target (IV will crush faster)
        // Very High > 350     → ₹18,000 target (extreme IV days)
        $suggestedTarget = match(true) {
            $totalPremiumPts > 350 => 18000,
            $totalPremiumPts > 270 => 16000,
            $totalPremiumPts > 200 => 14000,
            default                => 13000,
        };

        Log::info("ICL [{$tradeDate}] totalPremium={$totalPremiumPts} pts → suggestedTarget=₹{$suggestedTarget}");

        // Attach suggested target to each leg so command can pick it up
        foreach ($legs as &$leg) {
            $leg['suggested_target'] = $suggestedTarget;
        }

        return $legs;
    }

    public function describe(array $options): string
    {
        $qty = $options['lot'] ?? 65;
        return "Iron Condor Ladder — ATM + ±100 + ±300 CE+PE, {$qty} qty each (10 legs)";
    }
}
