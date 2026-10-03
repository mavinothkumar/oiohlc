<?php

namespace App\Domain\MarketRegime\Services;

class MarketRegimeEngine
{
    /**
     * Determine market regime transparently with individual contributing metrics.
     *
     * @param float $spot
     * @param array{
     *     vwap?: float,
     *     atr?: float,
     *     adx?: float,
     *     sma20?: float,
     *     sma50?: float,
     *     iv?: float,
     *     realized_vol?: float,
     *     intraday_high?: float,
     *     intraday_low?: float
     * } $indicators
     */
    public function evaluate(float $spot, array $indicators = []): array
    {
        $vwap = $indicators['vwap'] ?? $spot;
        $atr = $indicators['atr'] ?? ($spot * 0.008);
        $adx = $indicators['adx'] ?? 18.0;
        $sma20 = $indicators['sma20'] ?? $spot;
        $sma50 = $indicators['sma50'] ?? $spot;
        $iv = $indicators['iv'] ?? 0.14;
        $realizedVol = $indicators['realized_vol'] ?? 0.12;
        $dayHigh = $indicators['intraday_high'] ?? ($spot + $atr * 0.6);
        $dayLow = $indicators['intraday_low'] ?? ($spot - $atr * 0.6);

        $intradayRange = max(1.0, $dayHigh - $dayLow);
        $rangeAtrRatio = $intradayRange / max(1.0, $atr);

        $distFromVwapPct = (($spot - $vwap) / $spot) * 100.0;
        $distFromSma20Pct = (($spot - $sma20) / $spot) * 100.0;

        $contributingMeasurements = [
            'spot' => round($spot, 2),
            'vwap' => round($vwap, 2),
            'vwap_deviation_pct' => round($distFromVwapPct, 2),
            'atr_14' => round($atr, 2),
            'adx_14' => round($adx, 1),
            'intraday_range' => round($intradayRange, 2),
            'range_to_atr_ratio' => round($rangeAtrRatio, 2),
            'iv_level' => round($iv * 100, 2) . '%',
            'rv_level' => round($realizedVol * 100, 2) . '%',
            'trend_bias' => $spot > $sma20 && $sma20 > $sma50 ? 'BULLISH' : ($spot < $sma20 && $sma20 < $sma50 ? 'BEARISH' : 'NEUTRAL'),
        ];

        // Classification logic with deterministic rules
        if ($iv >= 0.22 || $rangeAtrRatio > 1.8) {
            $regime = 'HIGH_VOLATILITY';
            $description = 'Elevated volatility environment with expanded intraday range.';
        } elseif ($adx >= 28 && abs($distFromSma20Pct) > 0.8) {
            $regime = 'TREND';
            $description = 'Directional trending conditions with ADX > 28 and persistent price displacement.';
        } elseif ($iv < 0.11 && $rangeAtrRatio < 0.7) {
            $regime = 'LOW_VOLATILITY';
            $description = 'Compressed volatility and tight consolidated intraday range.';
        } elseif ($rangeAtrRatio < 1.2 && $adx < 22) {
            $regime = 'RANGE';
            $description = 'Mean-reverting consolidation with subdued directional momentum.';
        } elseif (abs($distFromVwapPct) > 0.6 || $rangeAtrRatio > 1.3) {
            $regime = 'TRANSITION';
            $description = 'Transitioning dynamics between balance and directional expansion.';
        } else {
            $regime = 'RANGE';
            $description = 'Standard range-bound market behavior.';
        }

        return [
            'regime' => $regime,
            'description' => $description,
            'metrics' => $contributingMeasurements,
        ];
    }
}
