<?php

// app/Services/Backtest/Strategies/FixedOffsetStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixedOffsetStrategy implements BacktestStrategy
{
    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        $offset = (int) ($options['strike-offset'] ?? 300);
        $atm    = (int)(round($indexOpen / 50) * 50);

        $upperStrike = $atm + $offset;
        $lowerStrike = $atm - $offset;

        $candidates = [
            ['strike' => $upperStrike, 'type' => 'CE'],
            ['strike' => $upperStrike, 'type' => 'PE'],
            ['strike' => $lowerStrike, 'type' => 'CE'],
            ['strike' => $lowerStrike, 'type' => 'PE'],
        ];

        $legs = $this->fetchLegs($symbol, $candidates, $entryTs);

        if (!$legs) {
            Log::info("FixedOffset SKIP [{$tradeDate}] — not enough legs found");
            return null;
        }

        // ── Dynamic target based on total premium collected ────────────
        $totalPremiumPts = array_sum(array_column($legs, 'entry_price'));

        $suggestedTarget = match (true) {
            $totalPremiumPts > 350 => 18000,
            $totalPremiumPts > 270 => 16000,
            $totalPremiumPts > 200 => 14000,
            default                => 13000,
        };

        Log::info("FixedOffset [{$tradeDate}] upper={$upperStrike} lower={$lowerStrike} totalPremium={$totalPremiumPts} pts → suggestedTarget=₹{$suggestedTarget}");

        foreach ($legs as &$leg) {
            $leg['suggested_target'] = $suggestedTarget;
        }

        return $legs;
    }

    public function describe(array $options): string
    {
        $offset = $options['strike-offset'] ?? 300;
        return "Fixed Offset ± {$offset}";
    }

    protected function fetchLegs(string $symbol, array $candidates, string $entryTs): ?array
    {
        $legs = [];

        foreach ($candidates as $candidate) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $candidate['type'])
                     ->where('strike', $candidate['strike'])
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryTs)
                     ->select('instrument_key', 'open')
                     ->first();

            if (!$row || (float) $row->open <= 0) {
                continue;
            }

            $legs[] = [
                'strike'           => $candidate['strike'],
                'type'             => $candidate['type'],
                'instrument_key'   => $row->instrument_key,
                'entry_price'      => (float) $row->open,
                'exit_price'       => null,
                'exit_time'        => null,
                'exited'           => false,
                'suggested_target' => null,  // filled after premium calc
                'qty_override'     => null,
            ];
        }

        return count($legs) >= 2 ? $legs : null;
    }
}
