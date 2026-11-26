<?php

namespace App\Http\Controllers;

use App\Models\ZeroClickAlertLog;
use App\Services\ZeroClickBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZeroClickBridgeController extends Controller
{
    protected ZeroClickBridgeService $bridge;

    public function __construct(ZeroClickBridgeService $bridge)
    {
        $this->bridge = $bridge;
    }

    public function logs(Request $request): JsonResponse
    {
        $query = ZeroClickAlertLog::orderByDesc('triggered_at')->orderByDesc('id');
        $category = strtoupper((string) $request->input('category', 'OIOHLC_V2'));
        if ($category !== 'ALL') {
            $query->where('category', $category);
        }
        if ($request->filled('date')) {
            $query->whereDate('trade_date', $request->input('date'));
        }
        if ($request->filled('mode') && $request->input('mode') !== 'ALL') {
            $query->where('mode', strtoupper((string) $request->input('mode')));
        }
        $logs = $query->limit(100)->get();
        return response()->json(['success' => true, 'logs' => $logs]);
    }

    public function clearLogs(Request $request): JsonResponse
    {
        ZeroClickAlertLog::where('category', 'OIOHLC_V2')->delete();
        return response()->json(['success' => true, 'message' => '0click V2 bridge logs cleared.']);
    }

    public function entry(Request $request): JsonResponse
    {
        $res = $this->bridge->dispatchEntry($request->all());
        return response()->json($res, $res['success'] ? 200 : 502);
    }

    public function exit(Request $request): JsonResponse
    {
        $res = $this->bridge->dispatchExit($request->all());
        return response()->json($res, $res['success'] ? 200 : 502);
    }

    public function syncSl(Request $request): JsonResponse
    {
        $res = $this->bridge->syncTrailingSl($request->all());
        return response()->json($res, $res['success'] ? 200 : 502);
    }

    public function resendLog(int $id): JsonResponse
    {
        $res = $this->bridge->resendLog($id);
        return response()->json($res, $res['success'] ? 200 : ($res['status_code'] ?? 502));
    }
}
