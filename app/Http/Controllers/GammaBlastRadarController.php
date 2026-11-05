<?php

namespace App\Http\Controllers;

use App\Services\Options\GammaExposureService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GammaBlastRadarController extends Controller
{
    protected GammaExposureService $gexService;

    public function __construct(GammaExposureService $gexService)
    {
        $this->gexService = $gexService;
    }

    /**
     * Main Gamma Blast Radar & GEX Cockpit View
     */
    public function index(Request $request): View
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $availableDates = $this->gexService->getAvailableDates($symbol);
        $today = Carbon::today('Asia/Kolkata')->toDateString();
        $latestDateWithData = $this->gexService->getLatestDateWithData($symbol);
        $selectedDate = $request->input('date', $latestDateWithData);
        $expiries = $this->gexService->getExpiries($selectedDate, $symbol);
        $selectedExpiry = $request->input('expiry', $expiries[0] ?? null);
        $selectedTime = $request->input('time', null);

        $gexData = $this->gexService->calculateGex($selectedDate, $selectedExpiry, $symbol, $selectedTime);

        return view('gamma-blast-radar.index', compact(
            'symbol',
            'availableDates',
            'selectedDate',
            'expiries',
            'selectedExpiry',
            'selectedTime',
            'gexData'
        ));
    }

    /**
     * AJAX endpoint for real-time auto-refresh, expiry switch, and timeline scrubbing
     */
    public function getData(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $expiry = $request->input('expiry', null);
        $time = $request->input('time', null);

        try {
            $data = $this->gexService->calculateGex($date, $expiry, $symbol, $time);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX endpoint to get expiries for a specific date
     */
    public function getExpiries(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'NIFTY');
        $date = $request->input('date', Carbon::today('Asia/Kolkata')->toDateString());
        $expiries = $this->gexService->getExpiries($date, $symbol);

        return response()->json([
            'success'  => true,
            'expiries' => $expiries,
        ]);
    }
}
