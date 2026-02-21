<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OhlcZoneController extends Controller
{
    public function index(Request $request)
    {
        // -----------------------------
        // 1. Static filter data
        // -----------------------------
        $instrumentTypes = ['CE', 'PE', 'FUT'];
        $underlyings     = ['NIFTY'];
        $intervals       = ['5minute', 'day'];

        // Keep all filters to refill the form
        $filters  = $request->all();
        $hasDate  = $request->filled('from_date') || $request->filled('to_date');
        $ohlc     = collect();   // default: empty
        $expiryDate = null;

        // -----------------------------
        // 2. Derive expiry from expired_expiries (if date is provided)
        // -----------------------------
        if ($hasDate) {
            // Pick a reference date: prefer from_date, else to_date
            $date = $request->input('from_date') ?? $request->input('to_date');

            // Map CE/PE/FUT to instrument_type used in expired_expiries
            $selectedInstrumentType = $request->input('instrument_type');

            if ($selectedInstrumentType === 'FUT') {
                $instrumentTypeForExpiry = 'FUT';
            } else {
                // CE, PE or empty -> treat as options
                $instrumentTypeForExpiry = 'OPT';
            }

            // Underlying (from filter; default NIFTY)
            $underlyingSymbol = $request->input('underlying_symbol', 'NIFTY');

            // Find nearest expiry ON or AFTER selected date
            // Change >= / orderBy if your business rule differs
            $expiryRow = DB::table('expired_expiries')
                           ->where('underlying_symbol', $underlyingSymbol)
                           ->where('instrument_type', $instrumentTypeForExpiry)
                           ->whereDate('expiry_date', '>=', $date)
                           ->orderBy('expiry_date', 'asc')
                           ->first(['expiry_date']);

            if ($expiryRow) {
                $expiryDate = $expiryRow->expiry_date;
            }
        }

        // -----------------------------
        // 3. Load OHLC data when we have date + expiry
        // -----------------------------
        if ($hasDate && $expiryDate) {
            $query = DB::table('expired_ohlc')
                       ->where('underlying_symbol', $request->input('underlying_symbol', 'NIFTY'))
                       ->whereDate('expiry', $expiryDate);

            // instrument_type: CE / PE / FUT (optional)
            if ($request->filled('instrument_type')) {
                $query->where('instrument_type', $request->instrument_type);
            }

            // interval: 5minute or day (optional, but you likely want it)
            if ($request->filled('interval')) {
                $query->where('interval', $request->interval);
            }

            // from / to date range on timestamp
            if ($request->filled('from_date')) {
                $query->whereDate('timestamp', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('timestamp', '<=', $request->to_date);
            }

            // Safety: hard limit rows to avoid memory blowups
            $ohlc = $query
                ->orderBy('timestamp', 'asc')
                ->limit(3000) // adjust as needed
                ->get([
                    'timestamp',
                    'open',
                    'high',
                    'low',
                    'close',
                    'volume',
                    'instrument_type',
                    'strike',
                    'expiry',
                    'interval',
                ]);
        }

        // -----------------------------
        // 4. Return view
        // -----------------------------
        return view('zones.index', [
            'ohlc'            => $ohlc,            // empty if no date or no expiry
            'instrumentTypes' => $instrumentTypes, // ['CE','PE','FUT']
            'underlyings'     => $underlyings,     // ['NIFTY']
            'intervals'       => $intervals,       // ['5minute','day']
            'filters'         => $filters,
            'hasDate'         => $hasDate,
            'expiryDate'      => $expiryDate,
        ]);
    }
}
