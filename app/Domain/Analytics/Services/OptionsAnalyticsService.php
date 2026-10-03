<?php

namespace App\Domain\Analytics\Services;

use App\Domain\ActionSimulator\Services\ActionSimulatorEngine;
use App\Domain\Breakeven\Services\BreakevenEngine;
use App\Domain\ExpectedMove\Services\ExpectedMoveEngine;
use App\Domain\Greeks\Services\GreeksEngine;
use App\Domain\Market\Services\MarketDataService;
use App\Domain\MarketRegime\Services\MarketRegimeEngine;
use App\Domain\Payoff\Services\PayoffEngine;
use App\Domain\Pnl\Services\PnlEngine;
use App\Domain\PositionState\Services\PositionStateEngine;
use App\Domain\Risk\Services\RiskEngine;
use App\Domain\Scenario\Services\ScenarioEngine;
use App\Domain\Strategy\Services\StrategyRegistry;
use App\Domain\Volatility\Services\VolatilityEngine;
use Carbon\Carbon;

class OptionsAnalyticsService
{
    public function __construct(
        protected GreeksEngine $greeksEngine = new GreeksEngine(),
        protected PnlEngine $pnlEngine = new PnlEngine(),
        protected BreakevenEngine $breakevenEngine = new BreakevenEngine(),
        protected PayoffEngine $payoffEngine = new PayoffEngine(),
        protected ScenarioEngine $scenarioEngine = new ScenarioEngine(),
        protected ExpectedMoveEngine $expectedMoveEngine = new ExpectedMoveEngine(),
        protected VolatilityEngine $volatilityEngine = new VolatilityEngine(),
        protected MarketRegimeEngine $regimeEngine = new MarketRegimeEngine(),
        protected RiskEngine $riskEngine = new RiskEngine(),
        protected PositionStateEngine $stateEngine = new PositionStateEngine(),
        protected ActionSimulatorEngine $actionSimulator = new ActionSimulatorEngine(),
        protected StrategyRegistry $strategyRegistry = new StrategyRegistry(),
        protected MarketDataService $marketData = new MarketDataService()
    ) {}

    /**
     * Compute full strategy-agnostic analytics suite for any position or leg collection.
     *
     * @param array $legs Normalized legs
     * @param float $spot Current underlying price
     * @param array $options Context including strategy_key, entry_assumptions, underlying, etc.
     * @return array
     */
    public function analyze(array $legs, float $spot, array $options = []): array
    {
        $underlying = $options['underlying'] ?? 'NIFTY';
        $strategyKey = $options['strategy_key'] ?? 'custom';
        $rate = (float)($options['rate'] ?? config('options.default_risk_free_rate', 0.07));
        $entrySnapshot = $options['entry_assumptions'] ?? [];
        $status = $options['status'] ?? 'OPEN';

        // 1. Resolve strategy definition
        $strategyDef = null;
        $strategyMeta = null;
        if ($this->strategyRegistry->has($strategyKey)) {
            $strategyDef = $this->strategyRegistry->get($strategyKey);
            $strategyMeta = [
                'key' => $strategyDef->key(),
                'name' => $strategyDef->name(),
                'description' => $strategyDef->description(),
                'category' => $strategyDef->category(),
                'characteristics' => $strategyDef->characteristics()->toArray(),
            ];
        }

        // 2. Greeks
        $greeksResult = $this->greeksEngine->calculatePositionGreeks($legs, $spot, $rate);
        $netGreeks = $greeksResult['total'];
        $legsGreeks = $greeksResult['legs'];

        // 3. P&L
        $pnlResult = $this->pnlEngine->calculatePositionPnl($legs);

        // 4. Breakevens
        $breakevenResult = $this->breakevenEngine->calculate($legs, $spot);

        // 5. Payoff Curve (Expiry + T+0)
        $payoffResult = $this->payoffEngine->generate($legs, $spot, null, null, 70, $rate);

        // 6. Scenarios (Spot × IV matrix)
        $scenarioMatrix = $this->scenarioEngine->generateMatrix($legs, $spot, null, null, 0, $rate);

        // 7. Expected Move
        $avgDte = !empty($legs) ? (float)($legs[0]['dte'] ?? 3.0) : 3.0;
        $avgIv = !empty($legs) ? (float)($legs[0]['iv'] ?? 0.14) : 0.14;
        $emResults = $this->expectedMoveEngine->calculateAll($spot, $avgDte, ['iv' => $avgIv]);

        // 8. Volatility Analytics
        $entryIv = (float)($entrySnapshot['iv'] ?? $avgIv);
        $volMetrics = $this->volatilityEngine->analyze($avgIv, $entryIv);

        // 9. Market Regime
        $regime = $this->regimeEngine->evaluate($spot, ['iv' => $avgIv]);

        // 10. Exposure
        $exposure = $this->riskEngine->calculateExposure($legsGreeks, $netGreeks);

        // 11. Position State & Explainability
        $entrySpot = (float)($entrySnapshot['spot'] ?? $spot);
        $expectedMoveVal = $emResults['primary']['expected_move'] ?? 200.0;
        $stateResult = $this->stateEngine->evaluate(
            greeks: $netGreeks,
            breakevens: $breakevenResult,
            spot: $spot,
            entrySpot: $entrySpot,
            iv: $avgIv,
            entryIv: $entryIv,
            dte: $avgDte,
            expectedMove: $expectedMoveVal,
            unrealizedPnl: $pnlResult->unrealizedPnl,
            maxLoss: $breakevenResult->maxLoss,
            status: $status
        );

        // 12. Original-Assumption Tracking
        $currentAssumptionSnapshot = [
            'spot' => $spot,
            'iv' => $avgIv,
            'delta' => $netGreeks->delta,
            'gamma' => $netGreeks->gamma,
            'theta' => $netGreeks->theta,
            'vega' => $netGreeks->vega,
            'dte' => $avgDte,
            'expected_move' => $expectedMoveVal,
        ];
        $originalAssumptionComparison = $this->riskEngine->trackOriginalAssumptions(
            $entrySnapshot ?: $currentAssumptionSnapshot,
            $currentAssumptionSnapshot
        );

        // Data provenance and calculation integrity (Section 41)
        $meta = [
            'calculation_timestamp' => Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s'),
            'market_data_timestamp' => Carbon::now('Asia/Kolkata')->format('H:i:s'),
            'data_source' => $this->marketData->getProviderName(),
            'analytics_version' => '1.0',
            'spot' => round($spot, 2),
            'underlying' => $underlying,
            'rate' => $rate,
            'dte' => $avgDte,
        ];

        return [
            'meta' => $meta,
            'strategy' => $strategyMeta,
            'greeks' => [
                'net' => $netGreeks->toArray(),
                'legs' => $legsGreeks,
            ],
            'pnl' => $pnlResult->toArray(),
            'breakevens' => $breakevenResult->toArray(),
            'payoff' => $payoffResult->toArray(),
            'scenarios' => $scenarioMatrix->toArray(),
            'expected_move' => $emResults,
            'volatility' => $volMetrics,
            'regime' => $regime,
            'exposure' => $exposure->toArray(),
            'risk_state' => $stateResult->toArray(),
            'assumptions_comparison' => $originalAssumptionComparison,
            'legs' => $legs,
        ];
    }

    public function simulateAction(array $legs, array $action, float $spot, float $rate = 0.07): array
    {
        return $this->actionSimulator->simulate($legs, $action, $spot, $rate)->toArray();
    }

    public function getStrategyRegistry(): StrategyRegistry
    {
        return $this->strategyRegistry;
    }

    public function getMarketDataService(): MarketDataService
    {
        return $this->marketData;
    }
}
