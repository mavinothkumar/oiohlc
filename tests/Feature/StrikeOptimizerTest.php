<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StrikeOptimizerTest extends TestCase
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

        if (!Schema::hasTable('daily_trend')) {
            Schema::create('daily_trend', function (Blueprint $table) {
                $table->id();
                $table->string('symbol_name', 32);
                $table->date('trading_date');
                $table->decimal('current_day_index_open', 12, 2)->nullable();
                $table->decimal('index_high', 12, 2)->nullable();
                $table->decimal('index_low', 12, 2)->nullable();
                $table->decimal('index_close', 12, 2)->nullable();
                $table->timestamps();
            });
            DB::table('daily_trend')->insert([
                'symbol_name' => 'NIFTY',
                'trading_date' => '2026-10-01',
                'current_day_index_open' => 22550.00,
                'index_high' => 22600.00,
                'index_low' => 22500.00,
                'index_close' => 22570.00,
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
        }

        DB::table('backtest_strategies')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Daily Short Straddle',
                'slug' => 'daily-short-straddle',
                'version' => 1,
                'definition' => json_encode([
                    'legs' => [
                        ['lots' => 1, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'CE', 'strike_offset' => 0],
                        ['lots' => 1, 'side' => 'SELL', 'moneyness' => 'ATM', 'option_type' => 'PE', 'strike_offset' => 0],
                    ]
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('backtest_strategies')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'OTM Strangle 100',
                'slug' => 'otm-strangle-100',
                'version' => 1,
                'definition' => json_encode([
                    'legs' => [
                        ['lots' => 2, 'side' => 'SELL', 'moneyness' => 'OTM', 'option_type' => 'CE', 'strike_offset' => 50],
                        ['lots' => 2, 'side' => 'SELL', 'moneyness' => 'OTM', 'option_type' => 'PE', 'strike_offset' => 50],
                    ]
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

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
        }

        // Insert sample strikes for 2026-10-01
        $strikes = [22400, 22450, 22500, 22550, 22600, 22650, 22700];
        $times = ['2026-10-01 09:15:00', '2026-10-01 10:00:00', '2026-10-01 15:30:00'];
        foreach ($strikes as $s) {
            foreach ($times as $idx => $t) {
                DB::table('option_chains')->insert([
                    ['trading_symbol' => 'NIFTY', 'expiry' => '2026-10-06', 'strike_price' => $s, 'option_type' => 'CE', 'ltp' => 120 - ($idx * 3), 'volume' => 4000, 'oi' => 80000, 'diff_oi' => 100, 'vega' => 9.0, 'theta' => -10.0, 'gamma' => 0.001, 'delta' => 0.5, 'iv' => 13.0, 'underlying_spot_price' => 22550, 'captured_at' => $t],
                    ['trading_symbol' => 'NIFTY', 'expiry' => '2026-10-06', 'strike_price' => $s, 'option_type' => 'PE', 'ltp' => 110 - ($idx * 2), 'volume' => 4000, 'oi' => 80000, 'diff_oi' => 100, 'vega' => 9.0, 'theta' => -10.0, 'gamma' => 0.001, 'delta' => -0.5, 'iv' => 13.0, 'underlying_spot_price' => 22550, 'captured_at' => $t],
                ]);
            }
        }
    }

    /**
     * Test Strike Optimizer page loads and contains strategy dropdown and single table.
     */
    public function test_strike_optimizer_page_loads_with_strategy_dropdown(): void
    {
        $response = $this->get('/strike-optimizer?date=2026-10-01+09%3A15%3A00&end_date=2026-10-01+15%3A30%3A00&expiry=2026-10-06');
        $response->assertStatus(200);

        // Check strategy dropdown present
        $response->assertSee('Strategy (/backtest/strategies)');
        $response->assertSee('Daily Short Straddle');
        $response->assertSee('OTM Strangle 100');

        // Check single performance table header with strategy name
        $response->assertSee('Strike Combinations Performance – Daily Short Straddle');

        // Verify the old hardcoded sections are removed
        $response->assertDontSee('Strike Combinations Performance (ATM Strikes)');
        $response->assertDontSee('Strike Combinations Performance (OTM Only – No ATM)');
    }

    /**
     * Test selecting a different strategy via dropdown updates table.
     */
    public function test_strike_optimizer_updates_on_strategy_change(): void
    {
        $response = $this->get('/strike-optimizer?strategy_id=2&date=2026-10-01+09%3A15%3A00&end_date=2026-10-01+15%3A30%3A00&expiry=2026-10-06');
        $response->assertStatus(200);

        // Table title matches selected strategy
        $response->assertSee('Strike Combinations Performance – OTM Strangle 100');
        $response->assertSee('Total Lots: 4L');
    }
}
