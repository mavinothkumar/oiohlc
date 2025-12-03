<?php

namespace App\Services;

use App\Models\DailyTrend;
use App\Models\DailyTrendMeta;
use App\Support\HlcEnums;
use Carbon\Carbon;

class HlcStrategyService
{
    public function evaluateForSymbol(
        DailyTrend $trend,
        float $spotLtp,
        ?float $ceLtp,
        ?float $peLtp,
        ?Carbon $recordedAt = null
    ): DailyTrendMeta {
        $recordedAt = $recordedAt ?: now();

        // 1. Compute basic flags (spot vs PDC equivalents)
        $spotAbovePdc = $spotLtp > $trend->strike;
        $spotBelowPdc = $spotLtp < $trend->strike;

        // 2. Map PDH/PDL equivalents from your static levels
        $pdh = $trend->max_r;  // as PDH equivalent
        $pdl = $trend->min_s;  // as PDL equivalent
        $pdc = $trend->strike; // as PDC equivalent

        $spotBreakRes = $spotLtp > $pdh;
        $spotBreakSup = $spotLtp < $pdl;

        $ceAbovePdh = $ceLtp !== null && $ceLtp > $trend->ce_high; // or PDH equivalent for CE
        $peAbovePdh = $peLtp !== null && $peLtp > $trend->pe_high;

        // 3. Build trigger list
        $triggers = [];
        if ($spotBreakRes) {
            $triggers[] = HlcEnums::TRIGGER_SPOT_BREAK_RES;
        }
        if ($spotBreakSup) {
            $triggers[] = HlcEnums::TRIGGER_SPOT_BREAK_SUP;
        }
        if ($spotAbovePdc) {
            $triggers[] = HlcEnums::TRIGGER_SPOT_ABOVE_PDC;
        }
        if ($spotBelowPdc) {
            $triggers[] = HlcEnums::TRIGGER_SPOT_BELOW_PDC;
        }
        if ($ceAbovePdh) {
            $triggers[] = HlcEnums::TRIGGER_CE_ABOVE_PDH;
        }
        if ($peAbovePdh) {
            $triggers[] = HlcEnums::TRIGGER_PE_ABOVE_PDH;
        }

        // 4. Determine seller status from CE/PE type (Profit/Panic/Side) based on your existing logic
        $callSellerStatus = $this->determineCallSellerStatus($trend, $ceLtp);
        $putSellerStatus  = $this->determinePutSellerStatus($trend, $peLtp);

        if ($callSellerStatus['panic']) {
            $triggers[] = HlcEnums::TRIGGER_CS_PANIC;
        }
        if ($putSellerStatus['panic']) {
            $triggers[] = HlcEnums::TRIGGER_PS_PANIC;
        }
        if ($callSellerStatus['profit_booking']) {
            $triggers[] = HlcEnums::TRIGGER_CS_PB;
        }
        if ($putSellerStatus['profit_booking']) {
            $triggers[] = HlcEnums::TRIGGER_PS_PB;
        }

        // 5. Map to high-level scenario
        [$scenario, $tradeSignal] = $this->decideScenarioAndSignal(
            $spotLtp,
            $trend,
            $triggers,
            $callSellerStatus,
            $putSellerStatus,
            $ceLtp,
            $peLtp
        );

        // 6. Build six levels status (min_r, max_r, min_s, max_s, earth_high, earth_low)
        $sixLevelsStatus = $this->buildSixLevelsStatus($trend, $spotLtp, $recordedAt);

        // 7. Sequence id (per symbol+date)
        $sequenceId = (int) DailyTrendMeta::where('daily_trend_id', $trend->id)
                                          ->whereDate('tracked_date', today())
                                          ->max('sequence_id') + 1;

        // 8. Persist meta snapshot
        return DailyTrendMeta::create([
            'daily_trend_id' => $trend->id,
            'tracked_date'   => today(),
            'recorded_at'    => $recordedAt,
            'sequence_id'    => $sequenceId,

            'ce_ltp'    => $ceLtp,
            'pe_ltp'    => $peLtp,
            'index_ltp' => $spotLtp,

            'market_scenario' => $scenario,
            'trade_signal'    => $tradeSignal,
            'triggers'        => $triggers,

            'pdh_equiv' => $pdh,
            'pdl_equiv' => $pdl,
            'pdc_equiv' => $pdc,

            'six_levels_status'  => $sixLevelsStatus,
            'call_seller_status' => $callSellerStatus,
            'put_seller_status'  => $putSellerStatus,

            'spot_above_pdc'        => $spotAbovePdc,
            'spot_below_pdc'        => $spotBelowPdc,
            'spot_break_resistance' => $spotBreakRes,
            'spot_break_support'    => $spotBreakSup,

            'ce_above_pdh' => $ceAbovePdh,
            'pe_above_pdh' => $peAbovePdh,
        ]);
    }

    // --------------- Helper methods ---------------

    private function determineCallSellerStatus(DailyTrend $trend, ?float $ceLtp): array
    {
        // reuse your existing type logic: Profit / Panic / Side
        // placeholder: you can refine using ce_high/low/close vs ce_ltp
        return [
            'panic'          => false,
            'profit_booking' => false,
            'type'           => 'Side',
        ];
    }

    private function determinePutSellerStatus(DailyTrend $trend, ?float $peLtp): array
    {
        return [
            'panic'          => false,
            'profit_booking' => false,
            'type'           => 'Side',
        ];
    }

    private function decideScenarioAndSignal(
        float $spotLtp,
        DailyTrend $trend,
        array $triggers,
        array $csStatus,
        array $psStatus,
        ?float $ceLtp,
        ?float $peLtp
    ): array {
        // Example mapping matching the PDF flow:
        // CSP-PSPB block (upside):
        if (in_array(HlcEnums::TRIGGER_SPOT_BREAK_RES, $triggers, true)) {
            // Scenario 1: If spot breaks resistance range → BUY CE
            return [HlcEnums::SCENARIO_CSP_PSPB, HlcEnums::SIGNAL_BUY_CE];
        }

        // CSPB-PSP block (downside):
        if (in_array(HlcEnums::TRIGGER_SPOT_BREAK_SUP, $triggers, true)) {
            // Scenario 1: If spot breaks support range → BUY PE
            return [HlcEnums::SCENARIO_CSPB_PSP, HlcEnums::SIGNAL_BUY_PE];
        }

        // Both Profit Booking (BOTHPB) block:
        if ($csStatus['profit_booking'] && $psStatus['profit_booking']) {
            return [HlcEnums::SCENARIO_BOTHPB, HlcEnums::SIGNAL_SIDEWAYS];
        }

        // Indecision block:
        if ( ! $csStatus['panic'] && ! $psStatus['panic'] && ! $csStatus['profit_booking'] && ! $psStatus['profit_booking']) {
            return [HlcEnums::SCENARIO_INDECISION, HlcEnums::SIGNAL_SIDEWAYS];
        }

        // Default: no clear signal
        return [null, null];
    }

    private function buildSixLevelsStatus(DailyTrend $trend, float $spotLtp, Carbon $time): array
    {
        $levels = [
            'min_r'      => $trend->min_r,
            'max_r'      => $trend->max_r,
            'min_s'      => $trend->min_s,
            'max_s'      => $trend->max_s,
            'earth_high' => $trend->earth_high,
            'earth_low'  => $trend->earth_low,
        ];

        $status = [];
        foreach ($levels as $name => $price) {
            if ($price === null) {
                continue;
            }

            $status[$name] = [
                'crossed'  => $spotLtp >= $price,
                'ltp_diff' => $spotLtp - $price,
                'last_at'  => $time->toDateTimeString(),
            ];
        }

        return $status;
    }
}
