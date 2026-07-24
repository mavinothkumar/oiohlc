<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StrategyPanel;
use App\Models\StrategyPanelLeg;
use Illuminate\Support\Facades\DB;
use App\Models\Instrument;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TradingJournalController extends Controller
{
    public function index()
    {
        return view('trading-journal.index');
    }

    public function getPanels()
    {
        $panels = StrategyPanel::with('legs')->orderBy('id', 'desc')->get();

        $currentExpiry = DB::table('nse_expiries')->where('is_current', 1)->value('expiry');
        // Fallback if no next flag, just get the next date after current
        $nextExpiry = DB::table('nse_expiries')
            ->where('expiry', '>', $currentExpiry)
            ->orderBy('expiry', 'asc')
            ->value('expiry');

        foreach ($panels as $panel) {
            // Find closest ohlc_quotes time matching panel->entry_time for today
            $today = Carbon::today()->format('Y-m-d');
            $entryDateTime = $today . ' ' . $panel->entry_time;

            foreach ($panel->legs as $leg) {
                $expiryToUse = $leg->expiry_type === 'Next' ? $nextExpiry : $currentExpiry;

                // Find instrument key
                $instrument = Instrument::where('name', 'NIFTY')
                    ->where('strike_price', $leg->strike_price)
                    ->where('instrument_type', $leg->option_type)
                    ->where('expiry', $expiryToUse)
                    ->first();

                $leg->instrument_key = $instrument ? $instrument->instrument_key : null;
                $leg->entry_price = 0;

                if ($leg->instrument_key) {
                    // Fetch entry price
                    $quote = DB::table('ohlc_quotes')
                        ->where('instrument_key', $leg->instrument_key)
                        ->where('ts_at', '>=', $entryDateTime)
                        ->orderBy('ts_at', 'asc')
                        ->first();

                    if ($quote) {
                        $leg->entry_price = $quote->open; // or close, depending on preference
                    }
                }
            }
        }

        return response()->json($panels);
    }

    public function savePanel(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:strategy_panels,id',
            'name' => 'required|string',
            'entry_time' => 'required|date_format:H:i',
            'legs' => 'required|array',
            'legs.*.strike_price' => 'required|numeric',
            'legs.*.option_type' => 'required|string|in:CE,PE',
            'legs.*.expiry_type' => 'required|string|in:Current,Next',
            'legs.*.quantity' => 'required|integer',
            'legs.*.side' => 'required|string|in:Buy,Sell',
        ]);

        $panel = StrategyPanel::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            ['name' => $validated['name'], 'entry_time' => $validated['entry_time']]
        );

        $panel->legs()->delete();
        foreach ($validated['legs'] as $legData) {
            $panel->legs()->create($legData);
        }

        return response()->json(['success' => true, 'panel' => $panel->load('legs')]);
    }

    public function deletePanel($id)
    {
        StrategyPanel::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function getWsUrl()
    {
        $token = config('services.upstox.analytics_token');
        if (!$token) {
            return response()->json(['error' => 'Upstox access token not configured'], 400);
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->get('https://api.upstox.com/v3/feed/market-data-feed/authorize');

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['error' => 'Failed to fetch WS URL', 'details' => $response->body()], $response->status());
    }
}
