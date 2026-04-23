<?php

// app/Services/SmartStrikeResolver.php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SmartStrikeResolver
{
    /**
     * Resolve balanced CE+PE strike pair for one side (upper or lower).
     *
     * @param string $symbol        e.g. NIFTY
     * @param float  $indexOpen     Index price at entry
     * @param string $side          'upper' or 'lower'
     * @param string $tradeDate     Y-m-d
     * @param string $entryTs       Full datetime string
     * @param int    $minOffset     300
     * @param int    $maxOffset     600
     * @param int    $stepSize      Strike step e.g. 100 for NIFTY
     * @return array|null           ['ce_strike', 'pe_strike', 'ce_price', 'pe_price', 'offset_used']
     */
    public function resolve(
        string $symbol,
        float  $indexOpen,
        string $side,
        string $tradeDate,
        string $entryTs,
        int    $minOffset = 300,
        int    $maxOffset = 600,
        int    $stepSize  = 100,
    ): ?array {
        $atm = (int) (round($indexOpen / 100) * 100);

        // Build candidate strikes from minOffset to maxOffset
        // Upper side: ATM + offset (CE is OTM, PE is ITM)
        // Lower side: ATM - offset (PE is OTM, CE is ITM)
        $candidates = [];

        for ($offset = $minOffset; $offset <= $maxOffset; $offset += $stepSize) {
            $strike = $side === 'upper'
                ? $atm + $offset
                : $atm - $offset;

            $cePrice = $this->getPrice($symbol, $strike, 'CE', $tradeDate, $entryTs);
            $pePrice = $this->getPrice($symbol, $strike, 'PE', $tradeDate, $entryTs);

            if ($cePrice === null || $pePrice === null) {
                continue;
            }

            $candidates[] = [
                'strike'     => $strike,
                'ce_price'   => $cePrice,
                'pe_price'   => $pePrice,
                'offset'     => $offset,
                'price_diff' => abs($cePrice - $pePrice),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        // ── Strategy: find the pair where CE ≈ PE (min price diff) ────────
        // But both must be at least ₹50 (filter out near-zero OTM garbage)
        $valid = array_filter($candidates, fn($c) =>
            $c['ce_price'] >= 20 && $c['pe_price'] >= 20
        );

        if (!empty($valid)) {
            // Sort by smallest price difference
            usort($valid, fn($a, $b) => $a['price_diff'] <=> $b['price_diff']);
            $best = reset($valid);

            return [
                'ce_strike'   => $best['strike'],
                'pe_strike'   => $best['strike'],
                'ce_price'    => $best['ce_price'],
                'pe_price'    => $best['pe_price'],
                'offset_used' => $best['offset'],
                'balanced'    => true,
                'strategy'    => 'same_strike_balanced',
            ];
        }

        // ── Fallback: anchor on highest-priced leg, walk for nearest match ─
        // Take the starting (min offset) strike as anchor
        $anchorCandidate = $candidates[0] ?? null;
        if (!$anchorCandidate) {
            return null;
        }

        $anchorStrike = $anchorCandidate['strike'];
        $ceAnchor     = $anchorCandidate['ce_price'];
        $peAnchor     = $anchorCandidate['pe_price'];

        // Determine which side is the anchor (higher price)
        if ($ceAnchor >= $peAnchor) {
            // CE is anchor — walk outward to find PE strike closest to CE price
            $anchorPrice   = $ceAnchor;
            $anchorType    = 'CE';
            $walkType      = 'PE';
            $anchorStrikeFinal = $anchorStrike;

            $walkStrike = $this->walkForMatch(
                symbol:      $symbol,
                targetPrice: $anchorPrice,
                type:        $walkType,
                atm:         $atm,
                side:        $side,
                tradeDate:   $tradeDate,
                entryTs:     $entryTs,
                minOffset:   $minOffset,
                maxOffset:   $maxOffset,
                stepSize:    $stepSize,
            );

            if (!$walkStrike) {
                return null;
            }

            return [
                'ce_strike'   => $anchorStrikeFinal,
                'pe_strike'   => $walkStrike['strike'],
                'ce_price'    => $anchorPrice,
                'pe_price'    => $walkStrike['price'],
                'offset_used' => $minOffset,
                'balanced'    => false,
                'anchor'      => 'CE',
                'strategy'    => 'cross_strike_balanced',
            ];

        } else {
            // PE is anchor — walk outward to find CE strike closest to PE price
            $anchorPrice       = $peAnchor;
            $anchorType        = 'PE';
            $anchorStrikeFinal = $anchorStrike;

            $walkStrike = $this->walkForMatch(
                symbol:      $symbol,
                targetPrice: $anchorPrice,
                type:        'CE',
                atm:         $atm,
                side:        $side,
                tradeDate:   $tradeDate,
                entryTs:     $entryTs,
                minOffset:   $minOffset,
                maxOffset:   $maxOffset,
                stepSize:    $stepSize,
            );

            if (!$walkStrike) {
                return null;
            }

            return [
                'ce_strike'   => $walkStrike['strike'],
                'pe_strike'   => $anchorStrikeFinal,
                'ce_price'    => $walkStrike['price'],
                'pe_price'    => $anchorPrice,
                'offset_used' => $minOffset,
                'balanced'    => false,
                'anchor'      => 'PE',
                'strategy'    => 'cross_strike_balanced',
            ];
        }
    }

    /**
     * Walk strikes outward from minOffset to maxOffset,
     * find the strike where `type` price is closest to `targetPrice`.
     */
    private function walkForMatch(
        string $symbol,
        float  $targetPrice,
        string $type,
        int    $atm,
        string $side,
        string $tradeDate,
        string $entryTs,
        int    $minOffset,
        int    $maxOffset,
        int    $stepSize,
    ): ?array {
        $best      = null;
        $bestDiff  = PHP_FLOAT_MAX;

        for ($offset = $minOffset; $offset <= $maxOffset; $offset += $stepSize) {
            $strike = $side === 'upper'
                ? $atm + $offset
                : $atm - $offset;

            $price = $this->getPrice($symbol, $strike, $type, $tradeDate, $entryTs);

            if ($price === null || $price < 20) {
                \Log::debug("Price is {$price} so Skipping");
                continue;
            }

            $diff = abs($price - $targetPrice);

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best     = ['strike' => $strike, 'price' => $price, 'diff' => $diff];
            }
        }

        return $best;
    }

    /**
     * Get the open price of a strike at entry time from expired_ohlc.
     */
    private function getPrice(
        string $symbol,
        int    $strike,
        string $type,
        string $tradeDate,
        string $entryTs,
    ): ?float {
        $row = DB::table('expired_ohlc')
                 ->where('underlying_symbol', $symbol)
                 ->where('instrument_type', $type)
                 ->where('strike', $strike)
                 ->where('interval', '5minute')
                 ->where('timestamp', $entryTs)
                 ->value('open');

        // Fallback: nearest candle on same date
        if ($row === null) {
            $row = DB::table('expired_ohlc')
                     ->where('underlying_symbol', $symbol)
                     ->where('instrument_type', $type)
                     ->where('strike', $strike)
                     ->where('interval', '5minute')
                     ->whereDate('timestamp', $tradeDate)
                     ->orderByRaw("ABS(TIMESTAMPDIFF(SECOND, timestamp, ?))", [$entryTs])
                     ->value('open');
        }

        return $row !== null ? (float) $row : null;
    }
}
