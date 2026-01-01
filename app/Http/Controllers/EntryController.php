<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\ExpiredOhlc;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    public function index()
    {
        $entries = Entry::latest()->get();

        return view('entries.index', compact('entries'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'underlying_symbol' => 'required',
            'exchange'          => 'required',
            'expiry'            => 'required|date',
            'instrument_type'   => 'required|in:CE,PE',
            'strike'            => 'required|integer',
            'side'              => 'required|in:BUY,SELL',
            'entry_date'        => 'required|date',
            'entry_time'        => 'required',
            'quantity'          => 'required|integer',
            'entry_price'       => 'required|numeric',
        ]);

        Entry::create($data);

        return redirect()->route('entries.index');
    }

    public function pnlSeries(Request $request)
    {
        $entries = Entry::all();
        $series  = [];
        $maxSteps = 0;

        $from = $request->query('from'); // e.g. "2024-10-30 09:45:00"

        foreach ($entries as $entry) {
            // default start = entry date + time
            $date = $entry->entry_date->format('Y-m-d');
            $time = \Carbon\Carbon::parse($entry->entry_time)->format('H:i:s');
            $entryStart = \Carbon\Carbon::parse("$date $time");

            // if user provided datetime, override start
            if ($from) {
                $entryStart = \Carbon\Carbon::parse($from);
            }

            $candles = ExpiredOhlc::where('underlying_symbol', $entry->underlying_symbol)
                                  ->where('exchange', $entry->exchange)
                                  ->where('expiry', $entry->expiry)
                                  ->where('instrument_type', $entry->instrument_type)
                                  ->where('strike', $entry->strike)
                                  ->where('interval', '5minute')
                                  ->where('timestamp', '>=', $entryStart)
                                  ->orderBy('timestamp')
                                  ->get(['timestamp', 'close']);

            $points = [];
            foreach ($candles as $c) {
                $ltp = (float) $c->close;
                $pnl = $this->pnlForEntry($entry, $ltp);
                $points[] = [
                    'time' => $c->timestamp->toDateTimeString(),
                    'ltp'  => $ltp,
                    'pnl'  => $pnl,
                ];
            }

            $maxSteps = max($maxSteps, count($points));

            $series[] = [
                'id'     => $entry->id,
                'script' => "{$entry->underlying_symbol} {$entry->expiry} {$entry->strike} {$entry->instrument_type}",
                'side'   => $entry->side,
                'qty'    => $entry->quantity,
                'entry'  => (float) $entry->entry_price,
                'points' => $points,
            ];
        }

        return response()->json([
            'series'   => $series,
            'maxSteps' => $maxSteps,
        ]);
    }



    public function destroy(Entry $entry)
    {
        $entry->delete();

        // for normal page request
        if (! request()->wantsJson()) {
            return redirect()->route('entries.index');
        }

        // for AJAX delete if you decide to use it
        return response()->json(['success' => true]);
    }

    // called every 0.5s via JS to update LTP + P&L
    public function pnlData()
    {
        $entries   = Entry::all();
        $replayTime = $this->currentReplayTime();

        $rows = [];
        $total = 0;

        foreach ($entries as $entry) {
            $ltp = $this->latestPriceForEntry($entry, $replayTime);
            $pnl = $ltp === null ? 0 : $this->pnlForEntry($entry, $ltp);
            $total += $pnl;

            $rows[] = [
                'id'     => $entry->id,
                'script' => "{$entry->underlying_symbol} {$entry->expiry} {$entry->strike} {$entry->instrument_type}",
                'side'   => $entry->side,
                'qty'    => $entry->quantity,
                'entry'  => $entry->entry_price,
                'ltp'    => $ltp,
                'pnl'    => $pnl,
            ];
        }

        return response()->json([
            'rows'       => $rows,
            'total'      => $total,
            'replayTime' => $replayTime->toDateTimeString(), // optional for display
        ]);
    }

    private function currentReplayTime(): Carbon
    {
        $time = session('replay_time');

        if (! $time) {
            // first 5‑min candle for the expiry in your entries
            $first = ExpiredOhlc::where('interval', '5minute')
                                ->orderBy('timestamp')
                                ->first();

            $time = $first ? Carbon::parse($first->timestamp) : now();
        } else {
            $time = Carbon::parse($time)->addMinutes(5); // step 1 candle
        }

        session(['replay_time' => $time]);

        return $time;
    }

    private function latestPriceForEntry(Entry $entry, Carbon $replayTime): ?float
    {
        // entry_date -> just date
        $date = $entry->entry_date instanceof Carbon
            ? $entry->entry_date->format('Y-m-d')
            : Carbon::parse($entry->entry_date)->format('Y-m-d');

        // entry_time currently is full datetime: "2025-12-31 09:40:00"
        // extract ONLY the time part "09:40:00"
        $time = $entry->entry_time instanceof Carbon
            ? $entry->entry_time->format('H:i:s')
            : Carbon::parse($entry->entry_time)->format('H:i:s');

        // now build one clean datetime: "2024-09-27 09:40:00"
        $entryStart = Carbon::parse($date . ' ' . $time);

        $candle = ExpiredOhlc::where('underlying_symbol', $entry->underlying_symbol)
                             ->where('exchange', $entry->exchange)
                             ->where('expiry', $entry->expiry)
                             ->where('instrument_type', $entry->instrument_type)
                             ->where('strike', $entry->strike)
                             ->where('interval', '5minute')
            ->where('timestamp', '>=', $entryStart)  // from entry onward
            ->where('timestamp', '<=', $replayTime)  // up to current replay time
                             ->orderByDesc('timestamp')
                             ->first();

        return $candle?->close;
    }

    private function pnlForEntry(Entry $entry, float $ltp): float
    {
        // Long: (LTP - entry) * qty, Short: (entry - LTP) * qty[web:14][web:17]
        if ($entry->side === 'BUY') {
            return ($ltp - $entry->entry_price) * $entry->quantity;
        }

        return ($entry->entry_price - $ltp) * $entry->quantity;
    }
}
