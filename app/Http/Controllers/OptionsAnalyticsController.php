<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Services\OptionsAnalyticsService;
use App\Domain\Market\Services\MarketDataService;
use App\Domain\Position\Services\PositionService;
use App\Domain\Strategy\Services\StrategyRegistry;
use App\Models\AnalyticsAuditLog;
use App\Models\Position;
use App\Models\PositionLeg;
use App\Models\Underlying;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OptionsAnalyticsController extends Controller
{
    public function __construct(
        protected OptionsAnalyticsService $analyticsService,
        protected PositionService $positionService,
        protected StrategyRegistry $strategyRegistry,
        protected MarketDataService $marketData
    ) {}

    /**
     * Main Strategy-Agnostic Options Analytics & Risk Terminal.
     */
    public function index(Request $request, ?int $positionId = null): View
    {
        $this->positionService->ensureDefaultUnderlyings();
        $this->positionService->syncStrategies();

        // Ensure at least demo positions exist
        if (Position::count() === 0) {
            $this->positionService->seedDemoPositions();
        }

        $allPositions = Position::with(['strategy', 'underlying', 'legs'])
            ->latest()
            ->get();

        // Selected position
        $activePosition = $positionId
            ? Position::with(['strategy', 'underlying', 'legs'])->find($positionId)
            : $allPositions->first();

        if (!$activePosition && $allPositions->isNotEmpty()) {
            $activePosition = $allPositions->first();
        }

        // Available strategies & indices
        $registeredStrategies = $this->strategyRegistry->toList();
        $underlyings = Underlying::where('status', 'ACTIVE')->get();

        // Quote & Analytics
        $underlyingSymbol = $activePosition?->underlying?->symbol ?? 'NIFTY';
        $quote = $this->marketData->getQuote($underlyingSymbol);

        $analytics = $activePosition
            ? $this->positionService->getPositionAnalytics($activePosition)
            : null;

        // Recent Audit / Risk Events for timeline
        $timelineEvents = $activePosition
            ? AnalyticsAuditLog::where('position_id', $activePosition->id)->latest()->take(10)->get()
            : collect();

        return view('options-analytics.index', compact(
            'allPositions',
            'activePosition',
            'registeredStrategies',
            'underlyings',
            'quote',
            'analytics',
            'timelineEvents'
        ));
    }

    /**
     * API: Get real-time recalculated analytics for an active position.
     */
    public function analytics(Position $position): JsonResponse
    {
        $data = $this->positionService->getPositionAnalytics($position);
        return response()->json($data);
    }

    /**
     * API: Action Simulator sandbox.
     */
    public function simulateAction(Request $request, Position $position): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|array',
            'action.type' => 'required|string',
            'action.leg_index' => 'nullable|integer',
            'action.new_strike' => 'nullable|numeric',
            'action.new_price' => 'nullable|numeric',
            'action.quantity' => 'nullable|integer',
            'action.hedge_side' => 'nullable|string',
            'action.hedge_type' => 'nullable|string',
            'action.hedge_strike' => 'nullable|numeric',
            'action.hedge_price' => 'nullable|numeric',
        ]);

        $legs = $this->positionService->getNormalizedLegs($position);
        $underlying = $position->underlying?->symbol ?? 'NIFTY';
        $spot = $this->marketData->getQuote($underlying)->spot;

        $result = $this->analyticsService->simulateAction($legs, $validated['action'], $spot);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * API: Create position from strategy preset.
     */
    public function createFromStrategy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'strategy_key' => 'required|string',
            'underlying_symbol' => 'nullable|string',
            'name' => 'nullable|string',
        ]);

        $underlying = $validated['underlying_symbol'] ?? 'NIFTY';
        $position = $this->positionService->createPositionFromStrategy(
            strategyKey: $validated['strategy_key'],
            underlyingSymbol: $underlying,
            name: $validated['name'] ?? null
        );

        return response()->json([
            'success' => true,
            'position_id' => $position->id,
            'redirect_url' => route('options-analytics.index', ['positionId' => $position->id]),
        ]);
    }

    /**
     * API: Update individual leg (e.g. quantity, strike, side).
     */
    public function updateLeg(Request $request, Position $position, PositionLeg $leg): JsonResponse
    {
        $validated = $request->validate([
            'side' => 'nullable|in:BUY,SELL',
            'quantity' => 'nullable|integer|min:1',
            'entry_price' => 'nullable|numeric',
            'current_price' => 'nullable|numeric',
            'strike' => 'nullable|numeric',
            'option_type' => 'nullable|in:CE,PE',
            'status' => 'nullable|in:OPEN,CLOSED',
        ]);

        $oldValues = $leg->toArray();

        if (isset($validated['side'])) $leg->side = $validated['side'];
        if (isset($validated['quantity'])) $leg->quantity = $validated['quantity'];
        if (isset($validated['entry_price'])) $leg->entry_price = $validated['entry_price'];
        if (isset($validated['current_price'])) $leg->current_price = $validated['current_price'];
        if (isset($validated['status'])) $leg->status = $validated['status'];

        $meta = $leg->metadata ?? [];
        if (isset($validated['strike'])) $meta['strike'] = $validated['strike'];
        if (isset($validated['option_type'])) $meta['option_type'] = $validated['option_type'];
        $leg->metadata = $meta;

        $leg->save();

        AnalyticsAuditLog::create([
            'position_id' => $position->id,
            'action' => 'LEG_UPDATED',
            'old_values' => $oldValues,
            'new_values' => $leg->fresh()->toArray(),
            'reason' => 'User adjustment from terminal interface',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leg updated successfully.',
            'analytics' => $this->positionService->getPositionAnalytics($position),
        ]);
    }
}
