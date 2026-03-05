<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BuildUpSnapshotController extends Controller
{
    public function index(Request $request)
    {
        $expiry = DB::table('nse_expiries')
                    ->where('is_current', 1)
                    ->where('instrument_type', 'OPT')
                    ->value('expiry_date');

        $underlying      = $request->get('underlying', 'Nifty');
        $underlyingLabel = $request->get('label', 'NIFTY');
        $date            = $request->get('date', Carbon::today()->toDateString());
        $top             = (int) $request->get('top', 10);
        $snapshot        = [];
        $spotPrice       = [];
        $allStrikes      = [];

        $rows = DB::table('option_chains')
                  ->whereDate('captured_at', $date)
                  ->where('expiry', $expiry)
                  ->where('trading_symbol', $underlying)
                  ->whereNotNull('build_up')
                  ->select(
                      DB::raw("DATE_FORMAT(captured_at, '%H:%i') as time_slot"),
                      'strike_price',
                      'option_type',
                      'diff_oi',
                      'diff_volume',
                      'build_up'
                  )
                  ->orderBy('captured_at', 'asc')
                  ->get();

        if (empty($rows)) {
            return view('buildups.snapshot', compact(
                'snapshot', 'expiry', 'spotPrice', 'underlyingLabel',
                'date', 'allStrikes', 'top'
            ));
        }

        // --- Independent top X lists ---

        // Top X by absolute diff_oi — each entry gets a global OI rank
        $topOiRows = $rows
            ->sortByDesc(fn($e) => abs($e->diff_oi))
            ->take($top)
            ->values()
            ->map(fn($e, $i) => [
                'time_slot'    => $e->time_slot,
                'strike_price' => $e->strike_price,
                'option_type'  => $e->option_type,
                'diff_oi'      => $e->diff_oi,
                'diff_volume'  => $e->diff_volume,
                'build_up'     => $e->build_up,
                'rank'         => $i + 1,
                'rank_type'    => 'OI',
            ]);

        // Top X by absolute diff_volume — each entry gets a global VOL rank
        $topVolRows = $rows
            ->sortByDesc(fn($e) => abs($e->diff_volume))
            ->take($top)
            ->values()
            ->map(fn($e, $i) => [
                'time_slot'    => $e->time_slot,
                'strike_price' => $e->strike_price,
                'option_type'  => $e->option_type,
                'diff_oi'      => $e->diff_oi,
                'diff_volume'  => $e->diff_volume,
                'build_up'     => $e->build_up,
                'rank'         => $i + 1,
                'rank_type'    => 'VOL',
            ]);

        // Merge both lists and group by time slot (preserving asc time order)
        $allRows = $topOiRows->merge($topVolRows);

        // Get ordered unique time slots from original rows to preserve asc order
        $orderedTimeSlots = $rows->pluck('time_slot')->unique()->values();


        foreach ($orderedTimeSlots as $timeSlot) {
            $slotRows = $allRows->filter(fn($e) => $e['time_slot'] === $timeSlot)->values();
            if ($slotRows->isNotEmpty()) {
                $snapshot[$timeSlot] = $slotRows;
            }
        }

        $spotPrice = DB::table('option_chains')
                       ->whereDate('captured_at', $date)
                       ->where('underlying_key', $underlying)
                       ->orderByDesc('captured_at')
                       ->value('underlying_spot_price');

        $allStrikes = $rows->pluck('strike_price');
        $snapshot   = array_reverse($snapshot, true);

        return view('buildups.snapshot', compact(
            'snapshot', 'expiry', 'spotPrice', 'underlyingLabel',
            'date', 'allStrikes', 'top'
        ));
    }
}
