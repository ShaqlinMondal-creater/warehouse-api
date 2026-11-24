<?php

namespace App\Http\Controllers;

use App\Services\GuestAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestAnalyticsController extends Controller
{
    public function __invoke(Request $request, GuestAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->forUser($request->user())]);
    }
}
