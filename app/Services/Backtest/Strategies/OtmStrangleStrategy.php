<?php

// app/Services/Backtest/Strategies/OtmStrangleStrategy.php

namespace App\Services\Backtest\Strategies;

use App\Services\Backtest\Contracts\BacktestStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OtmStrangleStrategy implements BacktestStrategy
{
    // Min premium required to take the trade
    private const MIN_PREMIUM = 50;

    // Starting offset from ATM
    private const START_OFFSET = 200;

    // Step inward toward ATM
    private const STEP = 100;

    public function resolveLegs(
        string $symbol,
        float  $indexOpen,
        string $tradeDate,
        string $entryTs,
        array  $options,
    ): ?array {
        $atm         = (int) (round($indexOpen / 100) * 100);
        $entryCandle = Carbon::parse($entryTs)->addMinutes(5)->toDateTimeString();
        $qty         = (int) ($options['lot'] ?? 130);
        $minPremium  = (float) ($options['min-premium'] ?? self::MIN_PREMIUM);

        $legs = [];

        foreach (['CE' => 1, 'PE' => -1] as $type => $direction) {
            $resolvedLeg = $this->resolveStrike(
                $symbol, $atm, $type, $direction,
                $entryCandle, $tradeDate, $minPremium, $qty
            );

            if (!$resolvedLeg) {
                \Log::info("OTM_STRANGLE SKIP [{$tradeDate}] — Could not find {$type} with premium ≥ ₹{$minPremium}");
                return null;
            }

            $legs[] = $resolvedLeg;
        }

        \Log::info("OTM_STRANGLE [{$tradeDate}] ATM={$atm} CE={$legs[0]['strike']}@{$legs[0]['entry_price']} PE={$legs[1]['strike']}@{$legs[1]['entry_price']} qty={$qty}");

        return $legs;
    }

    private function resolveStrike(
        string $symbol,
        int    $atm,
        string $type,
        int    $direction,   // +1 for CE (above ATM), -1 for PE (below ATM)
        string $entryCandle,
        string $tradeDate,
        float  $minPremium,
        int    $qty,
    ): ?array {
        // Start at ATM ± START_OFFSET, step inward by STEP
        $offset = self::START_OFFSET;

        while ($offset >= 0) {
            $strike = $atm + ($direction * $offset);

            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $type)
                     ->where('strike', $strike)
                     ->where('interval', '5minute')
                     ->where('timestamp', $entryCandle)
                     ->select('instrument_key', 'open', 'strike')
                     ->first();

            $price = $row ? (float) $row->open : 0;

            \Log::info("OTM_STRANGLE [{$tradeDate}] {$type} strike={$strike} price={$price}");

            if ($row && $price >= $minPremium) {
                return [
                    'strike'         => $strike,
                    'type'           => $type,
                    'instrument_key' => $row->instrument_key,
                    'entry_price'    => $price,
                    'exit_price'     => null,
                    'exit_time'      => null,
                    'exited'         => false,
                    'qty_override'   => $qty,
                ];
            }

            // Step inward toward ATM
            $offset -= self::STEP;
        }

        return null; // no strike found with sufficient premium
    }

    public function describe(array $options): string
    {
        $qty        = $options['lot']         ?? 130;
        $minPremium = $options['min-premium'] ?? self::MIN_PREMIUM;
        return "OTM Strangle — starts ATM±200, steps inward, min ₹{$minPremium} premium, {$qty} qty";
    }
}
