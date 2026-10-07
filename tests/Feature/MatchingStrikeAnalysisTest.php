<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MatchingStrikeAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure nse_working_days exists
        if (!Schema::hasTable('nse_working_days')) {
            Schema::create('nse_working_days', function (Blueprint $table) {
                $table->id();
                $table->date('working_date')->unique();
                $table->boolean('previous')->default(0);
                $table->boolean('current')->default(0);
                $table->timestamps();
            });
        }

        DB::table('nse_working_days')->updateOrInsert(
            ['working_date' => '2026-10-05'],
            ['previous' => 0, 'current' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        // Ensure option_chains table exists for test
        if (!Schema::hasTable('option_chains')) {
            Schema::create('option_chains', function (Blueprint $table) {
                $table->id();
                $table->string('instrument_key')->nullable();
                $table->string('underlying_key')->nullable();
                $table->string('trading_symbol');
                $table->date('expiry');
                $table->decimal('strike_price', 10, 2);
                $table->string('option_type', 5);
                $table->decimal('ltp', 10, 2)->default(0);
                $table->decimal('diff_ltp', 10, 2)->nullable();
                $table->bigInteger('volume')->default(0);
                $table->bigInteger('diff_volume')->default(0);
                $table->bigInteger('oi')->default(0);
                $table->bigInteger('diff_oi')->default(0);
                $table->decimal('close_price', 10, 2)->nullable();
                $table->decimal('bid_price', 10, 2)->nullable();
                $table->integer('bid_qty')->nullable();
                $table->decimal('ask_price', 10, 2)->nullable();
                $table->integer('ask_qty')->nullable();
                $table->bigInteger('prev_oi')->nullable();
                $table->decimal('vega', 8, 4)->nullable();
                $table->decimal('theta', 8, 4)->nullable();
                $table->decimal('gamma', 8, 4)->nullable();
                $table->decimal('delta', 8, 4)->nullable();
                $table->decimal('iv', 8, 4)->nullable();
                $table->decimal('pop', 8, 4)->nullable();
                $table->decimal('underlying_spot_price', 10, 2)->nullable();
                $table->decimal('pcr', 8, 4)->nullable();
                $table->string('build_up')->nullable();
                $table->dateTime('captured_at');
                $table->timestamps();
            });
        }

        $capturedAt = '2026-10-05 15:25:00';
        $currExpiry = '2026-10-06';
        $nextExpiry = '2026-10-13';

        // Clean any existing test rows for this date to avoid duplicates
        DB::table('option_chains')->whereDate('captured_at', '2026-10-05')->delete();

        // Seed sample data for both expiries
        DB::table('option_chains')->insert([
            // Current week CE & PE in ₹30 - ₹60 range
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $currExpiry,
                'strike_price' => 22650.00,
                'option_type' => 'CE',
                'ltp' => 41.50,
                'delta' => 0.3100,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $currExpiry,
                'strike_price' => 22450.00,
                'option_type' => 'PE',
                'ltp' => 42.10,
                'delta' => -0.3200,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            // Next week CE & PE in ₹30 - ₹60 range
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $nextExpiry,
                'strike_price' => 22800.00,
                'option_type' => 'CE',
                'ltp' => 54.00,
                'delta' => 0.2800,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $nextExpiry,
                'strike_price' => 22300.00,
                'option_type' => 'PE',
                'ltp' => 55.50,
                'delta' => -0.2900,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
        ]);
    }

    public function test_page_renders_successfully()
    {
        $response = $this->get(route('matching.strike.analysis', ['date' => '2026-10-05']));
        $response->assertStatus(200);
        $response->assertSee('Matching Strike Analysis');
        $response->assertSee('Current Week Expiry');
        $response->assertSee('Next Week Expiry');
        $response->assertSee('CE Strike');
        $response->assertSee('PE Strike');
        $response->assertSee('CE Dist');
        $response->assertSee('PE Dist');
        $response->assertSee('ATM');
    }

    public function test_strike_matcher_alias_route_works()
    {
        $response = $this->get('/strike-matcher?date=2026-10-05');
        $response->assertStatus(200);
        $response->assertSee('Matching Strike Analysis');
    }

    public function test_matching_strikes_pairs_within_price_range_and_diff()
    {
        $response = $this->get(route('matching.strike.analysis', [
            'date' => '2026-10-05',
            'min_price' => 30,
            'max_price' => 60,
            'max_delta' => 3.0,
        ]));

        $response->assertStatus(200);

        // Current week matched strike 22650 CE (41.50) vs 22450 PE (42.10), diff 0.60
        $response->assertSee('22,650');
        $response->assertSee('22,450');
        $response->assertSee('41.50');
        $response->assertSee('42.10');

        // Next week matched strike 22800 CE (54.00) vs 22300 PE (55.50), diff 1.50
        $response->assertSee('22,800');
        $response->assertSee('22,300');
        $response->assertSee('54.00');
        $response->assertSee('55.50');
    }

    public function test_filtering_by_custom_atm_and_range()
    {
        $response = $this->get(route('matching.strike.analysis', [
            'date' => '2026-10-05',
            'min_price' => 50,
            'max_price' => 60,
            'max_delta' => 2.0,
            'custom_atm' => 22500,
        ]));

        $response->assertStatus(200);
        // Next week pair is between 50 and 60, diff is 1.50 <= 2.0
        $response->assertSee('22,800');
        $response->assertSee('22,300');
    }

    public function test_loads_five_expiries_and_displays_correct_delta()
    {
        $capturedAt = '2026-10-05 15:25:00';
        $expiries = ['2026-10-06', '2026-10-13', '2026-10-20', '2026-10-27', '2026-11-03'];

        // Seed pairs across all 5 expiries
        foreach ($expiries as $idx => $exp) {
            DB::table('option_chains')->insert([
                [
                    'trading_symbol' => 'NIFTY',
                    'expiry' => $exp,
                    'strike_price' => 22600.00 + ($idx * 50),
                    'option_type' => 'CE',
                    'ltp' => 45.00,
                    'delta' => 0.3000,
                    'underlying_spot_price' => 22530.00,
                    'captured_at' => $capturedAt,
                ],
                [
                    'trading_symbol' => 'NIFTY',
                    'expiry' => $exp,
                    'strike_price' => 22400.00 - ($idx * 50),
                    'option_type' => 'PE',
                    'ltp' => 45.50,
                    'delta' => -0.3100,
                    'underlying_spot_price' => 22530.00,
                    'captured_at' => $capturedAt,
                ],
            ]);
        }

        $response = $this->get(route('matching.strike.analysis', ['date' => '2026-10-05']));
        $response->assertStatus(200);

        // Check that 5 expiries are present
        $response->assertSee('Current Week Expiry');
        $response->assertSee('Next Week Expiry');
        $response->assertSee('Expiry 3');
        $response->assertSee('Expiry 4');
        $response->assertSee('Expiry 5');

        // Check Delta and Net Delta values
        $response->assertSee('+0.300');
        $response->assertSee('-0.310');
        $response->assertSee('-0.010'); // Net delta: 0.300 + (-0.310) = -0.010
    }

    public function test_calculates_black_scholes_delta_when_delta_is_zero_or_null()
    {
        $capturedAt = '2026-10-05 15:25:00';
        $expiry = '2026-10-20';

        DB::table('option_chains')->insert([
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22700.00,
                'option_type' => 'CE',
                'ltp' => 40.00,
                'delta' => 0.0000, // zero delta in DB
                'iv' => 12.50,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22350.00,
                'option_type' => 'PE',
                'ltp' => 41.00,
                'delta' => 0.0000, // zero delta in DB
                'iv' => 12.50,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
        ]);

        $response = $this->get(route('matching.strike.analysis', ['date' => '2026-10-05']));
        $response->assertStatus(200);

        // It should calculate a non-zero Black-Scholes delta (approx 0.2-0.4 for near OTM)
        $response->assertDontSee('+0.000');
    }

    public function test_filters_by_max_greek_delta()
    {
        $capturedAt = '2026-10-05 15:25:00';
        $expiry = '2026-10-06';

        // High delta difference pair: CE delta 0.40, PE delta -0.20 (|0.40 - 0.20| = 0.20 diff)
        DB::table('option_chains')->insert([
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22620.00,
                'option_type' => 'CE',
                'ltp' => 38.00,
                'delta' => 0.4000,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22420.00,
                'option_type' => 'PE',
                'ltp' => 38.50,
                'delta' => -0.2000,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
        ]);

        // Filter with max_greek_delta = 0.20:
        // A pair with CE delta 0.40 must be excluded because 0.40 > 0.20
        $response = $this->get(route('matching.strike.analysis', [
            'date' => '2026-10-05',
            'max_greek_delta' => 0.20,
        ]));

        $response->assertStatus(200);
        // The strike 22,620 with delta 0.40 should be excluded
        $response->assertDontSee('22,620');
    }

    public function test_max_delta_strictly_filters_out_any_leg_exceeding_threshold()
    {
        $capturedAt = '2026-10-05 15:25:00';
        $expiry = '2026-10-13';

        // Pair A: CE delta 0.291, PE delta -0.298 (both > 0.20)
        // Pair B: CE delta 0.177, PE delta -0.180 (both <= 0.20)
        DB::table('option_chains')->insert([
            // Pair A
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22850.00,
                'option_type' => 'CE',
                'ltp' => 101.00,
                'delta' => 0.2910,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22250.00,
                'option_type' => 'PE',
                'ltp' => 101.50,
                'delta' => -0.2980,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            // Pair B
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 23100.00,
                'option_type' => 'CE',
                'ltp' => 43.00,
                'delta' => 0.1770,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
            [
                'trading_symbol' => 'NIFTY',
                'expiry' => $expiry,
                'strike_price' => 22000.00,
                'option_type' => 'PE',
                'ltp' => 43.50,
                'delta' => -0.1800,
                'underlying_spot_price' => 22530.00,
                'captured_at' => $capturedAt,
            ],
        ]);

        // When filtering with max_greek_delta = 0.20
        $response = $this->get(route('matching.strike.analysis', [
            'date' => '2026-10-05',
            'min_price' => 30,
            'max_price' => 200,
            'max_delta' => 2.0,
            'max_greek_delta' => 0.20,
        ]));

        $response->assertStatus(200);

        // Pair A (delta ~0.29) MUST NOT be present
        $response->assertDontSee('22,850');
        $response->assertDontSee('+0.291');
        $response->assertDontSee('-0.298');

        // Pair B (delta ~0.18 <= 0.20) MUST be present
        $response->assertSee('23,100');
        $response->assertSee('22,000');
        $response->assertSee('+0.177');
        $response->assertSee('-0.180');
    }
}
