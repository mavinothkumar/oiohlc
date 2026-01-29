<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketFlowController extends Controller
{
    public function index(Request $request)
    {
        // FILTER DEFAULTS
        $market      = $request->input('market', 'nifty'); // nifty, banknifty, sensex
        $type        = $request->input('type', 'fut'); // fut, opt
        $duration    = $request->input('duration', '1'); // 1 or 3
        $date        = $request->input('date', now()->toDateString());
        $startTime   = $date . " 09:15:00";
        $endTime     = $date . " 15:30:00";
        $limit       = $request->input('limit', 10);
        $range       = $request->input('range', $market === 'nifty' ? 300 : 1000);
        $strike      = $request->input('strike') ?? null; // Optionally for options

        // Which table to use
       $table = $duration === '1' ? 'full_market_quotes' : 'three_min_quotes';

        $currentExpiry = DB::table('nse_expiries')
                           ->where('trading_symbol', strtoupper($market))
                           ->where('instrument_type', strtoupper($type))
                           ->where('is_current', 1)
                           ->value('expiry_date');

        // Base Query
        $query = DB::table($table)
                   ->whereBetween('timestamp', [$startTime, $endTime])
                    ->where('expiry_date', $currentExpiry)
                    ->where('option_type', $type)
                    ->where('symbol_name', strtoupper($market));

        // Exclude 9:15-9:30 for ranking (but keep for display)
        $minRankTime = $date . " 09:30:00";

        // Get all data for the day for that symbol
        //$allData = $query->orderBy('timestamp')->toRawSql();
        $allData = $query->orderBy('timestamp')->get();
        //$allData->get();

        // OI/Volume Ranks (ignore early time for ranking)
        $rankWindow = collect($allData)->where('timestamp', '>=', $minRankTime);

        $ranks = [
            'oi'     => $rankWindow->sortBy('oi')->pluck('oi')->values()->all(),
            'volume' => $rankWindow->sortBy('volume')->pluck('volume')->values()->all(),
        ];

        // Label & classify each row
        $rows = [];
        foreach($allData as $ix => $row) {
            // Ranking (Position) in today’s range
            $oiRank     = ($row->timestamp >= $minRankTime) ? array_search($row->oi, $ranks['oi']) + 1 : null;
            $volumeRank = ($row->timestamp >= $minRankTime) ? array_search($row->volume, $ranks['volume']) + 1 : null;

            // Price move
            $priceMove = abs($row->close - $row->open);

            // Delta OI/Volume/Buy/Sell
            $diffoi = isset($row->diffoi) ? $row->diffoi : ($ix > 0 ? $row->oi - $allData[$ix-1]->oi : 0);
            $diffvol = isset($row->diffvolume) ? $row->diffvolume : ($ix > 0 ? $row->volume - $allData[$ix-1]->volume : 0);

            // Pressure (order book, if columns available)
            $buyQty   = property_exists($row, 'totalbuyquantity') ? $row->totalbuyquantity : 0;
            $sellQty  = property_exists($row, 'totalsellquantity') ? $row->totalsellquantity : 0;
            $ratio    = ($sellQty > 0) ? round($buyQty / $sellQty, 2) : 0;

            // Diagnostic Labels
            $status = null;
            if ($row->timestamp >= $minRankTime) {
                if ($oiRank && $oiRank <= 5 || $volumeRank && $volumeRank <= 5) {
                    if ($priceMove < ($row->close * 0.0015)) { // Low move, high activity
                        $status = 'absorption';
                    } else {
                        $status = 'breakout';
                    }
                } elseif ($oiRank && $oiRank > count($ranks['oi']) - 3) {
                    if ($ix < count($allData) - 2 && abs($allData[$ix+1]->close - $allData[$ix+1]->open) < ($row->close * 0.0007)) {
                        $status = 'false-breakout';
                    }
                }
            }

            $rows[] = (object)[
                ...get_object_vars($row),
                'oi_rank'          => $oiRank,
                'volume_rank'      => $volumeRank,
                'priceMove'        => $priceMove,
                'diffoi'           => $diffoi,
                'diffvolume'       => $diffvol,
                'book_ratio'       => $ratio,
                'status'           => $status,
            ];
        }

        // Sort rows by timestamp desc, and limit
        $rows = collect($rows)->sortByDesc('timestamp')->take($limit);

        // Pass all info to the blade
        return view('market-flow.index', [
            'rows'      => $rows,
            'markets'   => ['nifty', 'banknifty', 'sensex'],
            'type'      => $type,
            'market'    => $market,
            'duration'  => $duration,
            'date'      => $date,
            'limit'     => $limit,
            'range'     => $range,
        ]);
    }
}
