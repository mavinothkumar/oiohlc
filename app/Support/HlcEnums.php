<?php

namespace App\Support;

class HlcEnums
{
    // Main scenarios
    public const SCENARIO_CSP_PSPB = 'CSP-PSPB';
    public const SCENARIO_CSPB_PSP = 'CSPB-PSP';
    public const SCENARIO_BOTHPB   = 'BOTHPB';
    public const SCENARIO_INDECISION = 'INDECISION';

    // Trade signals
    public const SIGNAL_BUY_CE      = 'BUY_CE';
    public const SIGNAL_BUY_PE      = 'BUY_PE';
    public const SIGNAL_BUY_OPPOSITE = 'BUY_OPPOSITE';
    public const SIGNAL_LOW_CHANCE  = 'LOW_CHANCE';
    public const SIGNAL_SIDEWAYS    = 'SIDEWAYS';

    // Triggers (keys inside JSON triggers)
    public const TRIGGER_SPOT_BREAK_RES  = 'spot_break_resistance';
    public const TRIGGER_SPOT_BREAK_SUP  = 'spot_break_support';
    public const TRIGGER_SPOT_ABOVE_PDC  = 'spot_above_pdc';
    public const TRIGGER_SPOT_BELOW_PDC  = 'spot_below_pdc';
    public const TRIGGER_CS_PANIC        = 'call_seller_panic';
    public const TRIGGER_PS_PANIC        = 'put_seller_panic';
    public const TRIGGER_CS_PB           = 'call_seller_profit_booking';
    public const TRIGGER_PS_PB           = 'put_seller_profit_booking';
    public const TRIGGER_CE_ABOVE_PDH    = 'ce_above_pdh';
    public const TRIGGER_PE_ABOVE_PDH    = 'pe_above_pdh';
    public const TRIGGER_CS_PS_BELOW_6LV = 'cs_ps_below_6levels';
}
