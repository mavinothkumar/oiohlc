<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\StrategyPanel;
use App\Models\StrategyPanelLeg;

class GenerateDailyStrategies extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trading:generate-daily-strategies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generates daily ATM, ITM, OTM and Straddle panels based on NIFTY ATM Open';

    /**
     * Execute the console command.
     */
    public function handle() {
        $today = Carbon::today()->format( 'Y-m-d' );

        // 1. Get the current expiry date
        $currentExpiry = DB::table( 'nse_expiries' )
                           ->where( 'is_current', 1 )
                           ->value( 'expiry_date' );

        if ( ! $currentExpiry ) {
            $this->error( "No current expiry found in nse_expiries table." );

            return;
        }

        // 2. Fetch the ATM Index Open from the daily_trend table
        $trend = DB::table( 'daily_trend' )
                   ->where( 'trading_date', $today )
                   ->where( 'symbol_name', 'NIFTY' )
                   ->where( 'expiry_date', $currentExpiry )
                   ->first();

        if ( ! $trend || ! $trend->atm_index_open ) {
            $this->error( "No ATM index open found for NIFTY on {$today}. (Note: If market opens at 09:15, ensure your data is populated before this CRON runs)." );

            return;
        }

        // Ensure the ATM is perfectly rounded to the nearest 50 strike (e.g., 24000)
        $atm = round( $trend->atm_index_open / 50 ) * 50;
        $itm = $atm - 50;
        $otm = $atm + 50;

        $entryTime   = '09:16';
        $baseLotSize = 65; // Matches your UI frontend logic for 1 Lot

        // 3a. Define the raw, uncombined legs for Daily IAO
        $rawIaoLegs = [
            // --- ITM Legs ---
            [ 'strike' => $itm,       'type' => 'CE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $itm,       'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $itm - 50,  'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $itm + 50,  'type' => 'CE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $itm - 100, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $itm + 100, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],

            // --- ATM Legs ---
            [ 'strike' => $atm,       'type' => 'CE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $atm,       'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $atm - 50,  'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $atm + 50,  'type' => 'CE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $atm - 100, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $atm + 100, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],

            // --- OTM Legs ---
            [ 'strike' => $otm,       'type' => 'CE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $otm,       'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $otm - 50,  'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $otm + 50,  'type' => 'CE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $otm - 100, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
            [ 'strike' => $otm + 100, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
        ];

        // 3b. Consolidate duplicates for Daily IAO by adding their quantities together
        $consolidatedIaoLegs = [];
        foreach ($rawIaoLegs as $leg) {
            $key = $leg['strike'] . '_' . $leg['type'];
            if (isset($consolidatedIaoLegs[$key])) {
                $consolidatedIaoLegs[$key]['qty'] += $leg['qty'];
            } else {
                $consolidatedIaoLegs[$key] = $leg;
            }
        }

        // 3c. Define the final Panels and their Legs
        $strategies = [
            [
                'name' => 'Daily IAO',
                // We insert the cleanly merged array here
                'legs' => array_values($consolidatedIaoLegs),
            ],
            [
                'name' => 'Daily ITM',
                'legs' => [
                    [ 'strike' => $itm, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $itm, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $itm - 50, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $itm + 50, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $itm - 100, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $itm + 100, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                ],
            ],
            [
                'name' => 'Daily ATM',
                'legs' => [
                    [ 'strike' => $atm, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm - 50, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm + 50, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm - 100, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm + 100, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                ],
            ],
            [
                'name' => 'Daily OTM',
                'legs' => [
                    [ 'strike' => $otm, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $otm, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $otm - 50, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $otm + 50, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $otm - 100, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $otm + 100, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                ],
            ],
            [
                'name' => 'Straddle IAO',
                'legs' => [
                    [ 'strike' => $atm - 200, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm + 200, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm + 200, 'type' => 'CE', 'qty' => 1 * $baseLotSize ],
                    [ 'strike' => $atm - 200, 'type' => 'PE', 'qty' => 1 * $baseLotSize ],
                ],
            ],
            [
                'name' => 'Straddle ITM',
                'legs' => [
                    [ 'strike' => $itm, 'type' => 'CE', 'qty' => 2 * $baseLotSize ],
                    [ 'strike' => $itm, 'type' => 'PE', 'qty' => 2 * $baseLotSize ],
                ],
            ],
            [
                'name' => 'Straddle ATM',
                'legs' => [
                    [ 'strike' => $atm, 'type' => 'CE', 'qty' => 2 * $baseLotSize ],
                    [ 'strike' => $atm, 'type' => 'PE', 'qty' => 2 * $baseLotSize ],
                ],
            ],
            [
                'name' => 'Straddle OTM',
                'legs' => [
                    [ 'strike' => $otm, 'type' => 'CE', 'qty' => 2 * $baseLotSize ],
                    [ 'strike' => $otm, 'type' => 'PE', 'qty' => 2 * $baseLotSize ],
                ],
            ],

            [
                'name' => 'Strangle',
                'legs' => [
                    [ 'strike' => $atm + 200, 'type' => 'CE', 'qty' => 2 * $baseLotSize ],
                    [ 'strike' => $atm - 200, 'type' => 'PE', 'qty' => 2 * $baseLotSize ],
                ],
            ],

        ];

        // 4. Insert into Database
        DB::beginTransaction();
        try {

            StrategyPanelLeg::query()->delete();
            StrategyPanel::query()->delete();

            // Optional: If you want the IDs to reset back to 1 every day, uncomment the line below.
            // Note: Truncating might require disabling foreign key checks depending on your MySQL strict mode settings.
            // DB::statement('SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE strategy_panels; TRUNCATE TABLE strategy_panel_legs; SET FOREIGN_KEY_CHECKS=1;');

            foreach ( $strategies as $index => $strategy ) {
                $panel = StrategyPanel::create( [
                    'name'       => $strategy['name'],
                    'entry_time' => $entryTime,
                    'sort_order' => $index,
                ] );

                foreach ( $strategy['legs'] as $leg ) {
                    StrategyPanelLeg::create( [
                        'strategy_panel_id' => $panel->id,
                        'strike_price'      => $leg['strike'],
                        'option_type'       => $leg['type'],
                        'expiry_type'       => 'Current',
                        'quantity'          => $leg['qty'],
                        'side'              => 'Sell',
                    ] );
                }
            }
            DB::commit();
            $this->info( 'Daily strategies successfully generated for ATM: ' . $atm );
        } catch ( \Exception $e ) {
            DB::rollBack();
            $this->error( 'Failed to create strategies: ' . $e->getMessage() );
        }
    }
}
