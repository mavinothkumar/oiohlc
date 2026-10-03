<?php

namespace Tests\Feature;

use App\Domain\Position\Services\PositionService;
use App\Models\Position;
use Tests\TestCase;

class OptionsAnalyticsFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_03_174443_create_options_analytics_framework_tables.php',
        ]);

        app(PositionService::class)->seedDemoPositions();
    }

    /**
     * Test web dashboard route loads with HTTP 200.
     */
    public function test_options_analytics_dashboard_page_loads_successfully(): void
    {
        $response = $this->get('/options-analytics');
        $response->assertStatus(200);
        $response->assertSee('OPTIONS RISK TERMINAL');
        $response->assertSee('STRATEGY PAYOFF DIAGRAM');
    }

    /**
     * Test API returns full analytics payload.
     */
    public function test_api_options_analytics_data_returns_json(): void
    {
        $position = Position::first();
        $this->assertNotNull($position);

        $response = $this->getJson("/api/options-analytics/{$position->id}/data");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'meta',
            'strategy',
            'greeks' => ['net', 'legs'],
            'pnl',
            'breakevens',
            'payoff' => ['points', 'breakevens'],
            'scenarios' => ['spot_changes', 'iv_changes', 'matrix'],
            'expected_move',
            'volatility',
            'regime',
            'exposure',
            'risk_state' => ['state', 'reasons', 'summary'],
            'assumptions_comparison',
            'legs',
        ]);
    }

    /**
     * Test action simulator endpoint.
     */
    public function test_api_simulate_action_returns_before_after(): void
    {
        $position = Position::first();
        $this->assertNotNull($position);

        $response = $this->postJson("/api/options-analytics/{$position->id}/simulate-action", [
            'action' => [
                'type' => 'roll_strike',
                'leg_index' => 0,
                'new_strike' => 25300,
                'new_price' => 75.0,
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'action',
                'before',
                'after',
                'change',
                'improved',
                'increased_risks',
                'structural_changes',
            ]
        ]);
    }

    /**
     * Test creating a new position from preset (Iron Condor) proves strategy independence.
     */
    public function test_create_position_from_strategy_preset(): void
    {
        $response = $this->postJson('/api/options-analytics/create-from-strategy', [
            'strategy_key' => 'iron_condor',
            'underlying_symbol' => 'NIFTY',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $newPosId = $response->json('position_id');
        $this->assertNotNull($newPosId);

        $newPosition = Position::with('legs')->find($newPosId);
        $this->assertNotNull($newPosition);
        $this->assertCount(4, $newPosition->legs); // Iron Condor has 4 legs!
    }
}
