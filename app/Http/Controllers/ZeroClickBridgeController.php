<?php

namespace App\Http\Controllers;

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
}
