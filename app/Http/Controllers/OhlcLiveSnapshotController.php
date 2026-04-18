<?php

namespace App\Http\Controllers;

use App\Models\OhlcLiveSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OhlcLiveSnapshotController extends Controller
{
    /**
     * Display live OHLC + build-up table.
     * GET /live-ohlc
     */
    public function index(Request $request)
    {
        $symbol    = $request->input('symbol', 'NIFTY');
        $expiry    = $request->input('expiry');
        $buildUp   = $request->input('build_up');
        $type      = $request->input('instrument_type');
        $perPage   = (int) $request->input('per_page', 50);

        // Available symbols for filter dropdown
        $symbols = OhlcLiveSnapshot::distinct()
            ->orderBy('underlying_symbol')
            ->pluck('underlying_symbol');

        // Latest candle timestamp for the selected symbol
        $latestTs = OhlcLiveSnapshot::where('underlying_symbol', $symbol)
            ->orderByDesc('timestamp')
            ->value('timestamp');

        // Available expiries for symbol
        $expiries = OhlcLiveSnapshot::where('underlying_symbol', $symbol)
            ->distinct()
            ->orderBy('expiry_date')
            ->pluck('expiry_date');

        if (! $expiry && $expiries->isNotEmpty()) {
            $expiry = $expiries->first()->toDateString();
        }

        // ── Main query ─────────────────────────────────────────────────────
        $query = OhlcLiveSnapshot::where('underlying_symbol', $symbol)
            ->when($expiry,  fn($q) => $q->where('expiry_date', $expiry))
            ->when($buildUp, fn($q) => $q->where('build_up', $buildUp))
            ->when($type,    fn($q) => $q->where('instrument_type', $type))
            ->orderByDesc('timestamp')
            ->orderBy('strike');

        $snapshots = $query->paginate($perPage)->withQueryString();

        // ── Summary counts for build-up badges ────────────────────────────
        $buildUpCounts = OhlcLiveSnapshot::where('underlying_symbol', $symbol)
            ->when($expiry,  fn($q) => $q->where('expiry_date', $expiry))
            ->where('timestamp', $latestTs)
            ->whereNotNull('build_up')
            ->select('build_up', DB::raw('count(*) as total'))
            ->groupBy('build_up')
            ->pluck('total', 'build_up');

        return view('live-ohlc.index', compact(
            'snapshots',
            'symbols',
            'expiries',
            'symbol',
            'expiry',
            'buildUp',
            'type',
            'latestTs',
            'buildUpCounts',
            'perPage',
        ));
    }
}
