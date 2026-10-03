<?php

namespace App\Domain\Market\Providers;

use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\DTOs\OptionChainDTO;
use App\Domain\Market\DTOs\QuoteDTO;
use Carbon\Carbon;

class MockMarketDataProvider implements MarketDataProviderInterface
{
    public function name(): string
    {
        return 'Normalized Simulated Feed (NSE Reference)';
    }

    public function quote(string $symbol): QuoteDTO
    {
        $symbol = strtoupper($symbol);
        $config = config("options.indices.{$symbol}", config('options.indices.NIFTY'));

        $baseSpot = (float)($config['default_spot'] ?? 25250.0);
        // Small micro-fluctuation to show live responsiveness
        $timeVariance = (sin(time() / 15.0) * 8.5);
        $spot = round($baseSpot + $timeVariance, 2);

        $open = $baseSpot - 45.0;
        $high = $spot + 75.0;
        $low = $spot - 65.0;
        $close = $baseSpot - 12.0;
        $change = $spot - $close;
        $changePct = ($change / $close) * 100.0;
        $iv = 0.1385; // 13.85%
        $vwap = round($spot - 8.0, 2);

        return new QuoteDTO(
            symbol: $symbol,
            spot: $spot,
            bid: $spot - 0.5,
            ask: $spot + 0.5,
            high: $high,
            low: $low,
            open: $open,
            close: $close,
            change: $change,
            changePct: $changePct,
            volume: 18450000,
            iv: $iv,
            vwap: $vwap,
            timestamp: Carbon::now('Asia/Kolkata')->format('H:i:s'),
            source: $this->name()
        );
    }

    public function optionChain(string $underlying, ?string $expiry = null): OptionChainDTO
    {
        $quote = $this->quote($underlying);
        $spot = $quote->spot;
        $config = config("options.indices.{$underlying}", config('options.indices.NIFTY'));
        $step = (float)($config['strike_step'] ?? 50.0);

        $expiryDate = $expiry ?? Carbon::now('Asia/Kolkata')->addDays(3)->format('Y-m-d');
        $dte = 3.2; // ~3.2 days remaining
        $t = $dte / 365.0;
        $rate = 0.07;
        $baseIv = $quote->iv;

        $atm = round($spot / $step) * $step;
        $strikeList = [];

        // Generate ±10 strikes around ATM
        for ($i = -10; $i <= 10; $i++) {
            $strike = $atm + ($i * $step);
            // Volatility smile: OTM puts and calls have slightly higher IV
            $moneyness = log($strike / $spot);
            $strikeIv = $baseIv + (0.15 * ($moneyness * $moneyness)) - (0.05 * $moneyness);
            $strikeIv = max(0.08, min($strikeIv, 0.40));

            $ceGreeks = BlackScholesCalculator::calculate('CE', $spot, $strike, $t, $strikeIv, $rate);
            $peGreeks = BlackScholesCalculator::calculate('PE', $spot, $strike, $t, $strikeIv, $rate);

            $ceLtp = round($ceGreeks->theoreticalPrice, 2);
            $peLtp = round($peGreeks->theoreticalPrice, 2);

            $strikeList[] = [
                'strike' => (float)$strike,
                'is_atm' => ($strike == $atm),
                'ce' => [
                    'ltp' => $ceLtp,
                    'bid' => max(0.05, round($ceLtp - 0.25, 2)),
                    'ask' => round($ceLtp + 0.25, 2),
                    'iv' => round($strikeIv * 100, 2),
                    'delta' => round($ceGreeks->delta, 4),
                    'gamma' => round($ceGreeks->gamma, 6),
                    'theta' => round($ceGreeks->theta, 2),
                    'vega' => round($ceGreeks->vega, 2),
                    'volume' => rand(15000, 450000),
                    'oi' => rand(250000, 3500000),
                ],
                'pe' => [
                    'ltp' => $peLtp,
                    'bid' => max(0.05, round($peLtp - 0.25, 2)),
                    'ask' => round($peLtp + 0.25, 2),
                    'iv' => round($strikeIv * 100, 2),
                    'delta' => round($peGreeks->delta, 4),
                    'gamma' => round($peGreeks->gamma, 6),
                    'theta' => round($peGreeks->theta, 2),
                    'vega' => round($peGreeks->vega, 2),
                    'volume' => rand(15000, 450000),
                    'oi' => rand(250000, 3500000),
                ],
            ];
        }

        return new OptionChainDTO(
            underlying: $underlying,
            spot: $spot,
            expiry: $expiryDate,
            dte: $dte,
            strikes: $strikeList,
            timestamp: Carbon::now('Asia/Kolkata')->format('H:i:s'),
            source: $this->name()
        );
    }

    public function historical(string $symbol, string $timeframe = '1D', int $limit = 30): array
    {
        $base = $this->quote($symbol)->spot;
        $bars = [];
        for ($i = $limit; $i >= 0; $i--) {
            $date = Carbon::now('Asia/Kolkata')->subDays($i)->format('Y-m-d');
            $drift = sin($i * 0.3) * 120.0;
            $close = $base - $drift;
            $bars[] = [
                'date' => $date,
                'open' => round($close - 20, 2),
                'high' => round($close + 45, 2),
                'low' => round($close - 50, 2),
                'close' => round($close, 2),
                'volume' => rand(12000000, 22000000),
                'iv' => round(13.5 + sin($i * 0.4) * 2.0, 2),
            ];
        }
        return $bars;
    }
}
