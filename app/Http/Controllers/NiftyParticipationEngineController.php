<?php

namespace App\Http\Controllers;

use App\Services\NiftyParticipationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NiftyParticipationEngineController extends Controller
{
    public function __construct(
        protected NiftyParticipationService $participationService
    ) {}

    /**
     * Main View for Nifty 50 Participation & Directional Strength Engine.
     */
    public function index(Request $request): View
    {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();

        // 1. Fetch available trading dates efficiently from nse_working_days
        $availableDates = [];
        if (Schema::hasTable('nse_working_days')) {
            $availableDates = DB::table('nse_working_days')
                ->where('working_date', '<=', $todayStr)
                ->orderByDesc('working_date')
                ->limit(25)
                ->pluck('working_date')
                ->toArray();
        }

        if (empty($availableDates)) {
            $availableDates = [$todayStr];
        }

        // Fast resolution of latest quote date if available
        $latestQuoteDate = null;
        try {
            $latestTs = DB::table('ohlc_5m_quotes')
                ->where('instrument_key', 'like', '%Nifty 50%')
                ->orderByDesc('ts')
                ->value('ts_at');
            if ($latestTs) {
                $latestQuoteDate = substr($latestTs, 0, 10);
            }
        } catch (\Throwable $e) {
            $latestQuoteDate = null;
        }

        if ($latestQuoteDate && !in_array($latestQuoteDate, $availableDates)) {
            array_unshift($availableDates, $latestQuoteDate);
            $availableDates = array_values(array_unique($availableDates));
            rsort($availableDates);
        }

        $currentWorkingDate = null;
        if (Schema::hasTable('nse_working_days')) {
            $currentWorkingDate = DB::table('nse_working_days')
                ->where('current', 1)
                ->value('working_date');
        }

        $defaultDate = $currentWorkingDate ?: ($availableDates[0] ?? $todayStr);
        $selectedDate = $request->input('date', $defaultDate);

        $primaryTimeframe = (int)$request->input('tf1', 5);
        $secondaryTimeframe = (int)$request->input('tf2', 15);
        $basis = strtolower($request->input('basis', 'spot')) === 'future' ? 'future' : 'spot';

        $availableTimeframesPrimary = [
            1  => '1m',
            3  => '3m',
            5  => '5m',
            15 => '15m',
        ];

        $availableTimeframesSecondary = [
            15 => '15m',
            30 => '30m',
            60 => '60m',
            375 => '1D',
        ];

        return view('nifty-participation.index', compact(
            'availableDates',
            'selectedDate',
            'todayStr',
            'primaryTimeframe',
            'secondaryTimeframe',
            'basis',
            'availableTimeframesPrimary',
            'availableTimeframesSecondary'
        ));
    }

    /**
     * API Data Endpoint: Returns synchronized 4-pillar participation metrics,
     * constituent strength, momentum, 5m vs 15m consistency, and divergence diagnostics.
     */
    public function getData(Request $request): JsonResponse
    {
        $nowKolkata = Carbon::now('Asia/Kolkata');
        $todayStr = $nowKolkata->toDateString();
        $date = $request->input('date', $todayStr);

        $primaryInterval = (int)$request->input('tf1', $request->input('interval', 5));
        $secondaryInterval = (int)$request->input('tf2', 15);
        $basis = strtolower($request->input('basis', 'spot')) === 'future' ? 'future' : 'spot';
        $forceRefresh = $request->boolean('refresh', false);

        $data = $this->participationService->getParticipationData(
            $date,
            $primaryInterval,
            $secondaryInterval,
            $basis,
            $forceRefresh
        );

        return response()->json($data);
    }
}
