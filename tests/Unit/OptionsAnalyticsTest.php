<?php

namespace Tests\Unit;

use App\Domain\ActionSimulator\Services\ActionSimulatorEngine;
use App\Domain\Breakeven\Services\BreakevenEngine;
use App\Domain\Greeks\Calculators\BlackScholesCalculator;
use App\Domain\Greeks\Services\GreeksEngine;
use App\Domain\Payoff\Services\PayoffEngine;
use App\Domain\Pnl\Services\PnlEngine;
use App\Domain\PositionState\Services\PositionStateEngine;
use App\Domain\Scenario\Services\ScenarioEngine;
use App\Domain\Strategy\Services\StrategyRegistry;
use Tests\TestCase;

class OptionsAnalyticsTest extends TestCase
{
    protected GreeksEngine $greeksEngine;
    protected PnlEngine $pnlEngine;
    protected BreakevenEngine $breakevenEngine;
    protected PayoffEngine $payoffEngine;
    protected ScenarioEngine $scenarioEngine;
    protected PositionStateEngine $stateEngine;
    protected StrategyRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->greeksEngine = new GreeksEngine();
        $this->pnlEngine = new PnlEngine();
        $this->breakevenEngine = new BreakevenEngine();
        $this->payoffEngine = new PayoffEngine();
        $this->scenarioEngine = new ScenarioEngine();
        $this->stateEngine = new PositionStateEngine();
        $this->registry = new StrategyRegistry();
    }

    /**
     * 1. Strategy Registry tests
     */
    public function test_strategy_registry_contains_multiple_independent_strategies(): void
    {
        $this->assertTrue($this->registry->has('short_straddle'));
        $this->assertTrue($this->registry->has('long_straddle'));
        $this->assertTrue($this->registry->has('short_strangle'));
        $this->assertTrue($this->registry->has('iron_condor'));
        $this->assertTrue($this->registry->has('bull_call_spread'));

        $straddle = $this->registry->get('short_straddle');
        $this->assertEquals('Short Straddle', $straddle->name());
        $this->assertEquals('INCOME', $straddle->category());
        $this->assertFalse($straddle->characteristics()->isDefinedRisk);

        $condor = $this->registry->get('iron_condor');
        $this->assertTrue($condor->characteristics()->isDefinedRisk);
        $this->assertEquals(4, $condor->characteristics()->legCount);
    }

    /**
     * 2. Greeks Engine tests (individual legs & position sign conventions)
     */
    public function test_individual_leg_and_position_greeks_sign_conventions(): void
    {
        $spot = 25000.0;
        $strike = 25000.0;
        $dte = 3.0;
        $iv = 0.14;

        // Long Call: delta > 0, gamma > 0, theta < 0, vega > 0
        $longCall = $this->greeksEngine->calculateLegGreeks('BUY', 'CE', $spot, $strike, $dte, $iv, 1);
        $this->assertGreaterThan(0.45, $longCall['position']->delta);
        $this->assertLessThan(0.55, $longCall['position']->delta);
        $this->assertGreaterThan(0, $longCall['position']->gamma);
        $this->assertLessThan(0, $longCall['position']->theta);
        $this->assertGreaterThan(0, $longCall['position']->vega);

        // Short Call: delta < 0, gamma < 0, theta > 0, vega < 0
        $shortCall = $this->greeksEngine->calculateLegGreeks('SELL', 'CE', $spot, $strike, $dte, $iv, 1);
        $this->assertLessThan(0, $shortCall['position']->delta);
        $this->assertLessThan(0, $shortCall['position']->gamma);
        $this->assertGreaterThan(0, $shortCall['position']->theta);
        $this->assertLessThan(0, $shortCall['position']->vega);

        // Long Put: delta < 0, gamma > 0, theta < 0, vega > 0
        $longPut = $this->greeksEngine->calculateLegGreeks('BUY', 'PE', $spot, $strike, $dte, $iv, 1);
        $this->assertLessThan(0, $longPut['position']->delta);
        $this->assertGreaterThan(0, $longPut['position']->gamma);
        $this->assertLessThan(0, $longPut['position']->theta);
        $this->assertGreaterThan(0, $longPut['position']->vega);

        // Short Put: delta > 0, gamma < 0, theta > 0, vega < 0
        $shortPut = $this->greeksEngine->calculateLegGreeks('SELL', 'PE', $spot, $strike, $dte, $iv, 1);
        $this->assertGreaterThan(0, $shortPut['position']->delta);
        $this->assertLessThan(0, $shortPut['position']->gamma);
        $this->assertGreaterThan(0, $shortPut['position']->theta);
        $this->assertLessThan(0, $shortPut['position']->vega);

        // Multi-leg Short Straddle (Sell ATM Call + Sell ATM Put): Net delta near zero, negative gamma, positive theta
        $straddleLegs = [
            ['side' => 'SELL', 'option_type' => 'CE', 'strike' => 25000, 'dte' => 3.0, 'iv' => 0.14, 'quantity' => 1],
            ['side' => 'SELL', 'option_type' => 'PE', 'strike' => 25000, 'dte' => 3.0, 'iv' => 0.14, 'quantity' => 1],
        ];
        $posGreeks = $this->greeksEngine->calculatePositionGreeks($straddleLegs, $spot);
        $this->assertEqualsWithDelta(0.0, $posGreeks['total']->delta, 0.15); // near delta-neutral
        $this->assertLessThan(0, $posGreeks['total']->gamma); // short gamma
        $this->assertGreaterThan(0, $posGreeks['total']->theta); // positive theta
        $this->assertLessThan(0, $posGreeks['total']->vega); // short vega
    }

    /**
     * 3. P&L Engine tests
     */
    public function test_pnl_engine_buy_sell_and_intrinsic_values(): void
    {
        // Call intrinsic
        $this->assertEquals(100.0, $this->pnlEngine->calculateIntrinsic('CE', 25100, 25000));
        $this->assertEquals(0.0, $this->pnlEngine->calculateIntrinsic('CE', 24900, 25000));

        // Put intrinsic
        $this->assertEquals(100.0, $this->pnlEngine->calculateIntrinsic('PE', 24900, 25000));
        $this->assertEquals(0.0, $this->pnlEngine->calculateIntrinsic('PE', 25100, 25000));

        // BUY leg: entered at 100, now 150 -> profit = 50 * qty
        $buyPnl = $this->pnlEngine->calculateLegPnl('BUY', 100.0, 150.0, 10);
        $this->assertEquals(500.0, $buyPnl['gross_pnl']);

        // SELL leg: entered at 100, now 70 -> profit = 30 * qty
        $sellPnl = $this->pnlEngine->calculateLegPnl('SELL', 100.0, 70.0, 10);
        $this->assertEquals(300.0, $sellPnl['gross_pnl']);

        // Position PnL aggregation with costs
        $legs = [
            ['side' => 'SELL', 'entry_price' => 120.0, 'current_price' => 90.0, 'quantity' => 50, 'status' => 'OPEN'],
            ['side' => 'SELL', 'entry_price' => 110.0, 'current_price' => 80.0, 'quantity' => 50, 'status' => 'OPEN'],
        ];
        $posPnl = $this->pnlEngine->calculatePositionPnl($legs);
        $expectedGross = (30.0 * 50) + (30.0 * 50); // 1500 + 1500 = 3000
        $this->assertEquals($expectedGross, $posPnl->grossPnl);
        $this->assertGreaterThan(0, $posPnl->estimatedCosts);
        $this->assertEquals($posPnl->grossPnl - $posPnl->estimatedCosts, $posPnl->netPnl);
    }

    /**
     * 4. Breakeven & Payoff tests (Section 13 & 42)
     */
    public function test_short_straddle_breakevens_and_regions(): void
    {
        $strike = 25000.0;
        $cePremium = 100.0;
        $pePremium = 100.0;
        $totalPremium = $cePremium + $pePremium; // 200

        $legs = [
            ['side' => 'SELL', 'option_type' => 'CE', 'strike' => $strike, 'entry_price' => $cePremium, 'quantity' => 1],
            ['side' => 'SELL', 'option_type' => 'PE', 'strike' => $strike, 'entry_price' => $pePremium, 'quantity' => 1],
        ];

        $beResult = $this->breakevenEngine->calculate($legs, $strike);

        $this->assertCount(2, $beResult->breakevens);
        $this->assertEqualsWithDelta(24800.0, $beResult->breakevens[0], 0.5); // Lower BE = 25000 - 200
        $this->assertEqualsWithDelta(25200.0, $beResult->breakevens[1], 0.5); // Upper BE = 25000 + 200

        // Payoff tests at key zones
        // 1. Below Lower BE: Loss
        $pnlBelow = $this->breakevenEngine->evaluateExpiryPnl($legs, 24700.0);
        $this->assertEquals(-100.0, $pnlBelow);

        // 2. At Lower BE: 0
        $pnlLowerBe = $this->breakevenEngine->evaluateExpiryPnl($legs, 24800.0);
        $this->assertEquals(0.0, $pnlLowerBe);

        // 3. Between BEs: Profit
        $pnlBetween = $this->breakevenEngine->evaluateExpiryPnl($legs, 24900.0);
        $this->assertEquals(100.0, $pnlBetween);

        // 4. At Strike: Max Profit (200)
        $pnlStrike = $this->breakevenEngine->evaluateExpiryPnl($legs, 25000.0);
        $this->assertEquals(200.0, $pnlStrike);

        // 5. At Upper BE: 0
        $pnlUpperBe = $this->breakevenEngine->evaluateExpiryPnl($legs, 25200.0);
        $this->assertEquals(0.0, $pnlUpperBe);

        // 6. Above Upper BE: Loss
        $pnlAbove = $this->breakevenEngine->evaluateExpiryPnl($legs, 25300.0);
        $this->assertEquals(-100.0, $pnlAbove);
    }

    /**
     * 5. Scenario Engine tests
     */
    public function test_scenario_matrix_spot_iv_and_time_decay(): void
    {
        $legs = [
            ['side' => 'SELL', 'option_type' => 'CE', 'strike' => 25000, 'entry_price' => 110, 'quantity' => 50, 'dte' => 3.0, 'iv' => 0.14],
            ['side' => 'SELL', 'option_type' => 'PE', 'strike' => 25000, 'entry_price' => 110, 'quantity' => 50, 'dte' => 3.0, 'iv' => 0.14],
        ];

        $matrix = $this->scenarioEngine->generateMatrix($legs, 25000.0);
        $this->assertNotEmpty($matrix->matrix);

        // If Spot unchanged (0) and IV crushes (-5%), Short Straddle P&L should be positive
        $ivCrushCell = $matrix->matrix['0']['-0.05'];
        $this->assertGreaterThan(0, $ivCrushCell->pnl);

        // If Spot rallies +200 pts and IV spikes +5%, Short Straddle P&L should be negative
        $stressCell = $matrix->matrix['200']['0.05'];
        $this->assertLessThan(0, $stressCell->pnl);
    }

    /**
     * 6. Risk State Engine tests & explainability
     */
    public function test_position_risk_states_and_explainability(): void
    {
        $beResult = new \App\Domain\Breakeven\DTOs\BreakevenResult([24800, 25200], 200, null);

        // Case A: Normal baseline
        $normalGreeks = new \App\Domain\Greeks\DTOs\GreekSet(delta: 2.0, gamma: -0.002, theta: 45.0, vega: -80.0, rho: 0.0);
        $resNormal = $this->stateEngine->evaluate(
            greeks: $normalGreeks,
            breakevens: $beResult,
            spot: 25000.0,
            entrySpot: 25000.0,
            iv: 0.14,
            entryIv: 0.14,
            dte: 3.0,
            expectedMove: 210.0,
            unrealizedPnl: 100.0
        );
        $this->assertEquals('NORMAL', $resNormal->state);
        $this->assertNotEmpty($resNormal->reasons);

        // Case B: Caution state (Delta crossed warning threshold of 20)
        $cautionGreeks = new \App\Domain\Greeks\DTOs\GreekSet(delta: 24.0, gamma: -0.003, theta: 40.0, vega: -85.0, rho: 0.0);
        $resCaution = $this->stateEngine->evaluate(
            greeks: $cautionGreeks,
            breakevens: $beResult,
            spot: 25110.0,
            entrySpot: 25000.0,
            iv: 0.14,
            entryIv: 0.14,
            dte: 3.0,
            expectedMove: 210.0,
            unrealizedPnl: -200.0
        );
        $this->assertEquals('CAUTION', $resCaution->state);
        $this->assertStringContainsString('Net delta', $resCaution->reasons[0]);

        // Case C: Stress state (Breached Breakeven + Critical Delta)
        $stressGreeks = new \App\Domain\Greeks\DTOs\GreekSet(delta: 52.0, gamma: -0.015, theta: 20.0, vega: -120.0, rho: 0.0);
        $resStress = $this->stateEngine->evaluate(
            greeks: $stressGreeks,
            breakevens: $beResult,
            spot: 25280.0, // breached upper BE of 25200
            entrySpot: 25000.0,
            iv: 0.19, // IV spiked
            entryIv: 0.14,
            dte: 1.0,
            expectedMove: 210.0,
            unrealizedPnl: -1500.0
        );
        $this->assertContains($resStress->state, ['STRESS', 'RISK_REVIEW']);
        $this->assertStringContainsString('breached upper breakeven', implode(' ', $resStress->reasons));
    }

    /**
     * 7. Action Simulator test
     */
    public function test_action_simulator_before_after_changes(): void
    {
        $simulator = new ActionSimulatorEngine();
        $legs = [
            ['side' => 'SELL', 'option_type' => 'CE', 'strike' => 25000, 'entry_price' => 110, 'current_price' => 140, 'quantity' => 65, 'dte' => 3.0, 'iv' => 0.14],
            ['side' => 'SELL', 'option_type' => 'PE', 'strike' => 25000, 'entry_price' => 110, 'current_price' => 60, 'quantity' => 65, 'dte' => 3.0, 'iv' => 0.14],
        ];

        // Simulate rolling tested Call strike from 25000 to 25100
        $action = [
            'type' => 'roll_strike',
            'leg_index' => 0,
            'new_strike' => 25100,
            'new_price' => 85.0,
        ];

        $sim = $simulator->simulate($legs, $action, 25080.0);
        $this->assertNotEmpty($sim->beforeMetrics);
        $this->assertNotEmpty($sim->afterMetrics);
        $this->assertNotEmpty($sim->deltaMetrics);
        $this->assertNotEmpty($sim->actionDescription);
    }
}
