<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptionChainDiffController extends Controller
{
    // List of available trading symbols
    private const TRADING_SYMBOLS = [
        'NIFTY' => 'NIFTY 50',
        'BANKNIFTY' => 'BANK NIFTY',
        'SENSEX' => 'SENSEX'
    ];

    public function index(Request $request)
    {
        // Get parameters
        $tradingSymbol = $request->input('trading_symbol', 'NIFTY');
        $expiryDate = $request->input('expiry_date');
        $date = $request->input('date', now()->toDateString() . ' 09:30:00');

        // Get current expiry if not provided
        if (!$expiryDate) {
            $expiryDate = DB::table('expiries')
                            ->where('trading_symbol', $tradingSymbol)
                            ->where('is_current', 1)
                            ->where('instrument_type', 'OPT')
                            ->value('expiry_date');
        }

        // Fallback if no expiry found
        if (!$expiryDate) {
            return back()->with('error', 'No current expiry found for ' . $tradingSymbol);
        }

        // Fetch option chain data for the specific date - Get ALL records
        $optionChainData = DB::table('option_chains_3m')
                             ->where('trading_symbol', $tradingSymbol)
                             ->where('expiry', $expiryDate)
                             ->where('captured_at','>', $date)
                             ->orderBy('captured_at', 'desc')
                             ->get();

        if ($optionChainData->isEmpty()) {
            return back()->with('error', 'No option chain data found for the selected date and expiry');
        }

        // Get spot price (from latest record)
        $spotPrice = $optionChainData->first()->underlying_spot_price ?? 0;

        // Calculate strike range (±200 points from spot, max 8 strikes)
        $lowerBound = $spotPrice - 200;
        $upperBound = $spotPrice + 200;

        // Get strikes within range or nearest 8 strikes
        $allStrikes = $optionChainData->pluck('strike_price')->unique()->sort();

        $filteredStrikes = $allStrikes->filter(function($strike) use ($lowerBound, $upperBound) {
            return $strike >= $lowerBound && $strike <= $upperBound;
        });

        if ($filteredStrikes->isEmpty()) {
            // Get nearest 8 strikes to spot
            $filteredStrikes = $allStrikes->sortBy(function($strike) use ($spotPrice) {
                return abs($strike - $spotPrice);
            })->take(8)->sort();
        } else {
            $filteredStrikes = $filteredStrikes->take(8);
        }

        $strikes = $filteredStrikes->values()->all();

        // Filter data for these strikes
        $filteredData = $optionChainData->filter(function($item) use ($strikes) {
            return in_array($item->strike_price, $strikes);
        });

        // Separate CE and PE
        $ceData = $filteredData->where('option_type', 'CE');
        $peData = $filteredData->where('option_type', 'PE');

        // Build combined table structures (CE and PE side by side)
        $diffOiTable = $this->buildCombinedTable($ceData, $peData, $strikes, 'diff_oi');
        $diffVolumeTable = $this->buildCombinedTable($ceData, $peData, $strikes, 'diff_volume');

        return view('option-chain-diff', [
            'tradingSymbol' => $tradingSymbol,
            'tradingSymbols' => self::TRADING_SYMBOLS,
            'expiry' => $expiryDate,
            'spotPrice' => $spotPrice,
            'strikes' => $strikes,
            'diffOiTable' => $diffOiTable,
            'diffVolumeTable' => $diffVolumeTable,
            'selectedDate' => $date,
        ]);
    }

    private function buildCombinedTable($ceData, $peData, $strikes, $metricColumn)
    {
        $table = [];

        // Create 10 ranks (max)
        for ($rank = 1; $rank <= 10; $rank++) {
            $row = [
                'rank' => "#{$rank}",
                'ce' => [],
                'pe' => []
            ];

            // For each strike (column)
            foreach ($strikes as $strike) {
                $strikeKey = (string)intval($strike);

                // Get CE records for this strike, sorted by metric in descending order
                // First sort by build_up (prioritize records with build_up), then by metric
                $ceStrikeRecords = $ceData->where('strike_price', $strike)
                                          ->sortBy(function($item) {
                                              // Prioritize records with build_up (not null) over those without
                                              return is_null($item->build_up) ? 1 : 0;
                                          })
                                          ->sortByDesc(function($item) use ($metricColumn) {
                                              return $item->$metricColumn ?? 0;
                                          })
                                          ->values();

                // Get PE records for this strike, sorted by metric in descending order
                $peStrikeRecords = $peData->where('strike_price', $strike)
                                          ->sortBy(function($item) {
                                              return is_null($item->build_up) ? 1 : 0;
                                          })
                                          ->sortByDesc(function($item) use ($metricColumn) {
                                              return $item->$metricColumn ?? 0;
                                          })
                                          ->values();

                // Get CE value at this rank
                if ($rank <= $ceStrikeRecords->count()) {
                    $record = $ceStrikeRecords[$rank - 1];

                    // Safely get all values
                    $metricValue = (int)($record->$metricColumn ?? 0);
                    $time = date('H:i', strtotime($record->captured_at ?? now()));
                    $ltp = (float)($record->ltp ?? 0);
                    $oi = (int)($record->oi ?? 0);
                    $volume = (int)($record->volume ?? 0);
                    $buildUp = $record->build_up ?? null;

                    $row['ce'][$strikeKey] = [
                        'diff_value' => $metricValue,
                        'ltp' => $ltp,
                        'oi' => $oi,
                        'volume' => $volume,
                        'time' => $time,
                        'build_up' => $buildUp
                    ];
                } else {
                    $row['ce'][$strikeKey] = '-';
                }

                // Get PE value at this rank
                if ($rank <= $peStrikeRecords->count()) {
                    $record = $peStrikeRecords[$rank - 1];

                    // Safely get all values
                    $metricValue = (int)($record->$metricColumn ?? 0);
                    $time = date('H:i', strtotime($record->captured_at ?? now()));
                    $ltp = (float)($record->ltp ?? 0);
                    $oi = (int)($record->oi ?? 0);
                    $volume = (int)($record->volume ?? 0);
                    $buildUp = $record->build_up ?? null;

                    $row['pe'][$strikeKey] = [
                        'diff_value' => $metricValue,
                        'ltp' => $ltp,
                        'oi' => $oi,
                        'volume' => $volume,
                        'time' => $time,
                        'build_up' => $buildUp
                    ];
                } else {
                    $row['pe'][$strikeKey] = '-';
                }
            }

            $table[] = $row;
        }

        return $table;
    }
}
