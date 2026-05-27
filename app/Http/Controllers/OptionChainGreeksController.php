<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OptionChainGreeksController extends Controller
{
    public function index(Request $request)
    {
        $expiry = $request->input('expiry');

        if (!$expiry) {
            $expiry = DB::table('nse_expiries')
                        ->where('is_current', 1)
                        ->where('trading_symbol', 'NIFTY')
                        ->where('instrument_type', 'OPT')
                        ->value('expiry_date');
        }

        $date = $request->input('date', now()->toDateString());
        $strikeLeft  = $request->input('strike_left');
        $strikeRight = $request->input('strike_right');
        $enterPrice  = $request->input('enter_price');
        $viewMode    = $request->input('view_mode', 'combined');

        $expiries = DB::table('nse_expiries')
                      ->where('trading_symbol', 'NIFTY')
                      ->where('instrument_type', 'OPT')
                      ->orderBy('expiry_date')
                      ->get(['expiry_date', 'is_current', 'is_next']);

        $strikes = [];
        if ($expiry) {
            $strikes = DB::table('option_chains')
                         ->where('trading_symbol', 'NIFTY')
                         ->where('expiry', $expiry)
                         ->whereDate('captured_at', $date)
                         ->select('strike_price')
                         ->distinct()
                         ->orderBy('strike_price')
                         ->pluck('strike_price');
        }

        $leftData  = [];
        $rightData = [];
        $combinedPriceData = [];

        if ($expiry && $strikeLeft && $strikeRight) {
            $leftRaw = DB::table('option_chains')
                         ->where('trading_symbol', 'NIFTY')
                         ->where('expiry', $expiry)
                         ->where('strike_price', $strikeLeft)
                         ->whereDate('captured_at', $date)
                         ->orderBy('captured_at')
                         ->select('captured_at','option_type','ltp','vega','theta','gamma','delta','iv','pop')
                         ->get();

            $rightRaw = DB::table('option_chains')
                          ->where('trading_symbol', 'NIFTY')
                          ->where('expiry', $expiry)
                          ->where('strike_price', $strikeRight)
                          ->whereDate('captured_at', $date)
                          ->orderBy('captured_at')
                          ->select('captured_at','option_type','ltp','vega','theta','gamma','delta','iv','pop')
                          ->get();

            $leftData  = $this->processGreeks($leftRaw);
            $rightData = $this->processGreeks($rightRaw);
            $combinedPriceData = $this->combinedPrice($leftData, $rightData);
        }

        return view('option-chain-greeks', compact(
            'expiry',
            'date',
            'expiries',
            'strikes',
            'strikeLeft',
            'strikeRight',
            'enterPrice',
            'viewMode',
            'leftData',
            'rightData',
            'combinedPriceData'
        ));
    }

    private function processGreeks($rawData): array
    {
        $grouped = [];

        foreach ($rawData as $row) {
            $t = $row->captured_at;

            if (!isset($grouped[$t])) {
                $grouped[$t] = ['CE' => null, 'PE' => null];
            }

            $grouped[$t][$row->option_type] = $row;
        }

        $result = [
            'times' => [],
            'ltp' => [],
            'vega' => [],
            'theta' => [],
            'gamma' => [],
            'delta' => [],
            'iv' => [],
            'pop' => [],
        ];

        foreach ($grouped as $t => $g) {
            $ce = $g['CE'];
            $pe = $g['PE'];

            $ltp   = ($ce ? (float)$ce->ltp : 0)   + ($pe ? (float)$pe->ltp : 0);
            $vega  = ($ce ? (float)$ce->vega : 0)  + ($pe ? (float)$pe->vega : 0);
            $theta = ($ce ? (float)$ce->theta : 0) + ($pe ? (float)$pe->theta : 0);
            $gamma = ($ce ? (float)$ce->gamma : 0) + ($pe ? (float)$pe->gamma : 0);
            $delta = ($ce ? (float)$ce->delta : 0) + ($pe ? (float)$pe->delta : 0);
            $iv    = ($ce && $pe) ? (((float)$ce->iv + (float)$pe->iv) / 2) : ($ce ? (float)$ce->iv : ($pe ? (float)$pe->iv : 0));
            $pop   = ($ce && $pe) ? (((float)$ce->pop + (float)$pe->pop) / 2) : ($ce ? (float)$ce->pop : ($pe ? (float)$pe->pop : 0));

            $result['times'][] = date('H:i', strtotime($t));
            $result['ltp'][]   = round($ltp, 2);
            $result['vega'][]  = round($vega, 4);
            $result['theta'][] = round($theta, 4);
            $result['gamma'][] = round($gamma, 4);
            $result['delta'][] = round($delta, 4);
            $result['iv'][]    = round($iv, 2);
            $result['pop'][]   = round($pop, 2);
        }

        return $result;
    }

    private function combinedPrice(array $left, array $right): array
    {
        $rightMap = array_combine($right['times'] ?? [], $right['ltp'] ?? []);
        $combined = [];
        $times = [];

        foreach (($left['times'] ?? []) as $i => $t) {
            $lPrice = $left['ltp'][$i] ?? 0;
            $rPrice = $rightMap[$t] ?? 0;

            $combined[] = round($lPrice + $rPrice, 2);
            $times[] = $t;
        }

        return ['times' => $times, 'combined' => $combined];
    }
}
