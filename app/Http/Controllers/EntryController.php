<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\ExpiredOhlc;
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

    // called every 0.5s via JS to update LTP + P&L
    public function pnlData()
    {
        $entries = Entry::all();

        $rows = [];
        $total = 0;

        foreach ($entries as $entry) {
            $ltp = $this->latestPriceForEntry($entry);

            if ($ltp === null) {
                $pnl = 0;
            } else {
                $pnl = $this->pnlForEntry($entry, $ltp);
            }

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
            'rows'  => $rows,
            'total' => $total,
        ]);
    }

    private function latestPriceForEntry(Entry $entry): ?float
    {
        $candle = ExpiredOhlc::where('underlying_symbol', $entry->underlying_symbol)
                             ->where('exchange', $entry->exchange)
                             ->where('expiry', $entry->expiry)
                             ->where('instrument_type', $entry->instrument_type)
                             ->where('strike', $entry->strike)
                             ->where('interval', '5minute')
                             ->where('timestamp', '<=', now())
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
