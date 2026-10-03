<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StrategyPremiumAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('nse_working_days')) {
            Schema::create('nse_working_days', function (Blueprint $table) {
                $table->id();
                $table->date('working_date')->unique();
                $table->boolean('previous')->default(0);
                $table->boolean('current')->default(0);
                $table->timestamps();
            });
            DB::table('nse_working_days')->insert([
                'working_date' => today()->toDateString(),
                'previous' => 0,
                'current' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure backtest_strategies table exists in test DB
        if (!Schema::hasTable('backtest_strategies')) {
            Schema::create('backtest_strategies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->integer('version')->default(1);
                $table->json('definition');
                $table->json('parameters')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('backtest_strategies')->insert([
                'id' => 1,
                'name' => 'Daily OAI Test',
                'slug' => 'daily-oai-test',
                'version' => 1,
                'definition' => json_encode([
                    'legs' => [
                        ['lots' => 2, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'CE', 'strike_offset' => 0],
                        ['lots' => 2, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'PE', 'strike_offset' => 0],
                    ]
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure option_chains table exists in test DB
        if (!Schema::hasTable('option_chains')) {
            Schema::create('option_chains', function (Blueprint $table) {
                $table->id();
                $table->string('trading_symbol', 32)->default('NIFTY');
                $table->date('expiry')->default('2026-10-06');
                $table->decimal('strike_price', 12, 2)->default(22550.00);
                $table->string('option_type', 8)->default('CE');
                $table->decimal('ltp', 12, 2)->default(150.00);
                $table->bigInteger('volume')->default(1000);
                $table->bigInteger('oi')->default(50000);
                $table->decimal('diff_oi', 12, 2)->default(200);
                $table->decimal('vega', 10, 4)->default(10.5);
                $table->decimal('theta', 10, 4)->default(-12.0);
                $table->decimal('gamma', 10, 6)->default(0.001);
                $table->decimal('delta', 10, 4)->default(0.5);
                $table->decimal('iv', 8, 2)->default(13.5);
                $table->decimal('underlying_spot_price', 12, 2)->default(22550.00);
                $table->dateTime('captured_at')->default('2026-10-01 09:15:00');
            });

            // Insert CE and PE records across two timestamps
            $times = ['2026-10-01 09:15:00', '2026-10-01 09:30:00', '2026-10-01 10:00:00'];
            foreach ($times as $idx => $t) {
                DB::table('option_chains')->insert([
                    ['trading_symbol' => 'NIFTY', 'expiry' => '2026-10-06', 'strike_price' => 22550, 'option_type' => 'CE', 'ltp' => 150 - ($idx * 5), 'volume' => 5000, 'oi' => 100000, 'diff_oi' => 500, 'vega' => 10.5, 'theta' => -12.0, 'gamma' => 0.001, 'delta' => 0.50, 'iv' => 13.5, 'underlying_spot_price' => 22550, 'captured_at' => $t],
                    ['trading_symbol' => 'NIFTY', 'expiry' => '2026-10-06', 'strike_price' => 22550, 'option_type' => 'PE', 'ltp' => 140 - ($idx * 4), 'volume' => 5000, 'oi' => 100000, 'diff_oi' => 500, 'vega' => 10.5, 'theta' => -12.0, 'gamma' => 0.001, 'delta' => -0.50, 'iv' => 13.5, 'underlying_spot_price' => 22550, 'captured_at' => $t],
                ]);
            }
        }
    }

    /**
     * Test page loads with HTTP 200.
     */
    public function test_strategy_premium_analytics_page_loads_successfully(): void
    {
        $response = $this->get('/strategy-premium-analytics?date=2026-10-01&expiry=2026-10-06');
        $response->assertStatus(200);
        $response->assertSee('COMBINED STRATEGY MATRIX CHART');
        $response->assertSee('STRATEGY HEALTH');
    }

    /**
     * Test API endpoint returns JSON structure with combined metrics and judgment.
     */
    public function test_api_strategy_premium_analytics_data_returns_json(): void
    {
        $response = $this->getJson('/api/strategy-premium-analytics/data?strategy_id=1&symbol=NIFTY&date=2026-10-01&expiry=2026-10-06');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'strategy_name',
            'resolved_legs',
            'total_lots',
            'atm_strike',
            'summary' => [
                'start_time',
                'latest_time',
                'base_premium',
                'latest_premium',
                'net_decay_points',
                'decay_pct',
                'decay_velocity',
                'latest_vwap',
                'latest_delta',
            ],
            'judgment' => [
                'status',
                'headline',
                'reasons',
                'actionable_suggestion',
            ],
            'chart_data' => [
                'labels',
                'combined_ltp',
                'vwap',
                'oi_vwap',
                'net_oi',
                'delta',
                'gamma',
                'theta',
                'vega',
                'iv',
            ],
            'progression_table',
        ]);
    }

    /**
     * Test manual mode with custom strikes and lots.
     */
    public function test_manual_mode_with_strikes_and_lots(): void
    {
        $response = $this->get('/strategy-premium-analytics?mode=manual&date=2026-10-01&expiry=2026-10-06&call_strikes[]=22550&call_lots[]=2&put_strikes[]=22550&put_lots[]=3');
        $response->assertStatus(200);
        $response->assertSee('COMBINED STRATEGY MATRIX CHART');
    }

    /**
     * Test custom ATM override recalculates strategy strikes.
     */
    public function test_custom_atm_override_recalculates_strategy_strikes(): void
    {
        $response = $this->get('/strategy-premium-analytics?strategy_id=1&date=2026-10-01&expiry=2026-10-06&custom_atm=22600');
        $response->assertStatus(200);
        $response->assertSee('ATM: 22600');
        $response->assertSee('Custom');

        $apiResponse = $this->getJson('/api/strategy-premium-analytics/data?strategy_id=1&symbol=NIFTY&date=2026-10-01&expiry=2026-10-06&custom_atm=22600');
        $apiResponse->assertStatus(200);
        $apiResponse->assertJsonPath('atm_strike', 22600);
        $apiResponse->assertJsonPath('is_custom_atm', true);
    }

    /**
     * Test Basket Builder moneyness formula parity and option_chain_view matrix.
     */
    public function test_basket_builder_formula_parity_and_option_chain_view(): void
    {
        // Insert a multi-leg strategy matching Daily OAI definition
        DB::table('backtest_strategies')->updateOrInsert(
            ['id' => 99],
            [
                'name' => 'Parity Test Strategy',
                'slug' => 'parity-test-strategy',
                'version' => 1,
                'definition' => json_encode([
                    'legs' => [
                        ['lots' => 2, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'CE', 'strike_offset' => 0],
                        ['lots' => 2, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'PE', 'strike_offset' => 0],
                        ['lots' => 1, 'side' => 'SELL', 'moneyness' => 'ITM', 'option_type' => 'CE', 'strike_offset' => 0],
                        ['lots' => 3, 'side' => 'SELL', 'moneyness' => 'ITM', 'option_type' => 'PE', 'strike_offset' => 0],
                        ['lots' => 3, 'side' => 'SELL', 'moneyness' => 'OTM', 'option_type' => 'CE', 'strike_offset' => 0],
                        ['lots' => 1, 'side' => 'SELL', 'moneyness' => 'OTM', 'option_type' => 'PE', 'strike_offset' => 0],
                    ]
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $response = $this->getJson('/api/strategy-premium-analytics/data?strategy_id=99&symbol=NIFTY&date=2026-10-01&expiry=2026-10-06&custom_atm=22700');
        $response->assertStatus(200);

        $legs = $response->json('resolved_legs');
        // ATM: 22700
        $this->assertEquals(22700, $legs[0]['strike']); // CE ATM
        $this->assertEquals(22700, $legs[1]['strike']); // PE ATM
        // ITM offset 0 in NIFTY (step 50): 22700 - 50 = 22650
        $this->assertEquals(22650, $legs[2]['strike']); // CE ITM
        $this->assertEquals(22650, $legs[3]['strike']); // PE ITM
        // OTM offset 0 in NIFTY (step 50): 22700 + 50 = 22750
        $this->assertEquals(22750, $legs[4]['strike']); // CE OTM
        $this->assertEquals(22750, $legs[5]['strike']); // PE OTM

        // Option chain view check
        $chain = $response->json('option_chain_view');
        $this->assertNotEmpty($chain);
        // Find 22650: 1 CE lot, 3 PE lots
        $stk22650 = collect($chain)->firstWhere('strike', 22650);
        $this->assertNotNull($stk22650);
        $this->assertEquals(1, $stk22650['ce_lots']);
        $this->assertEquals(3, $stk22650['pe_lots']);

        // Find 22700 ATM: 2 CE lots, 2 PE lots
        $stk22700 = collect($chain)->firstWhere('strike', 22700);
        $this->assertNotNull($stk22700);
        $this->assertEquals(2, $stk22700['ce_lots']);
        $this->assertEquals(2, $stk22700['pe_lots']);
        $this->assertTrue($stk22700['is_atm']);

        // Find 22750: 3 CE lots, 1 PE lot
        $stk22750 = collect($chain)->firstWhere('strike', 22750);
        $this->assertNotNull($stk22750);
        $this->assertEquals(3, $stk22750['ce_lots']);
        $this->assertEquals(1, $stk22750['pe_lots']);
    }
}
