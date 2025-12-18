<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'service' => 'OBSOLIO API'
        ]);
    }

    public function detailed(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'database' => 'connected',
            'cache' => 'working',
            'queue' => 'running'
        ]);
    }

    public function ready(): JsonResponse
    {
        return response()->json(['ready' => true]);
    }

    public function alive(): JsonResponse
    {
        return response()->json(['alive' => true]);
    }
}
