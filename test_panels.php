<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StrategyPanel;
use App\Models\Instrument;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$panels = StrategyPanel::with('legs')->orderBy('id', 'desc')->get();
$currentExpiry = DB::table('nse_expiries')->where('is_current', 1)->value('expiry');
$nextExpiry = DB::table('nse_expiries')
    ->where('expiry', '>', $currentExpiry)
    ->orderBy('expiry', 'asc')
    ->value('expiry');

echo "Current Expiry: $currentExpiry\n";
echo "Next Expiry: $nextExpiry\n";

foreach ($panels as $panel) {
    echo "Panel: {$panel->name} (Entry Time: {$panel->entry_time})\n";
    $today = Carbon::today()->format('Y-m-d');
    $entryDateTime = $today . ' ' . $panel->entry_time;
    echo "Entry DateTime for lookup: $entryDateTime\n";

    foreach ($panel->legs as $leg) {
        $expiryToUse = $leg->expiry_type === 'Next' ? $nextExpiry : $currentExpiry;
        
        echo " Leg: {$leg->strike_price} {$leg->option_type} Expiry: $expiryToUse\n";
        
        $instrument = Instrument::where('name', 'NIFTY')
            ->where('strike_price', $leg->strike_price)
            ->where('instrument_type', $leg->option_type)
            ->where('expiry', $expiryToUse)
            ->first();
        
        if ($instrument) {
            echo "  Instrument Key: {$instrument->instrument_key}\n";
            $quote = DB::table('ohlc_quotes')
                ->where('instrument_key', $instrument->instrument_key)
                ->where('ts_at', '>=', $entryDateTime)
                ->orderBy('ts_at', 'asc')
                ->first();
                
            if ($quote) {
                echo "  Found Quote! Open: {$quote->open}, ts_at: {$quote->ts_at}\n";
            } else {
                echo "  No Quote found in ohlc_quotes >= $entryDateTime\n";
                // let's just see how many quotes we have for this instrument
                $count = DB::table('ohlc_quotes')->where('instrument_key', $instrument->instrument_key)->count();
                echo "  Total quotes for instrument in ohlc_quotes: $count\n";
            }
        } else {
            echo "  Instrument NOT FOUND for strike={$leg->strike_price} option_type={$leg->option_type} expiry=$expiryToUse\n";
        }
    }
}

