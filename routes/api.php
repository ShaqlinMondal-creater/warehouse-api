<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return [
        'ok' => true,
        'app' => config('warehouse.app.name'),
        'ai_enabled' => config('warehouse.ai.enabled'),
    ];
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/overview', OverviewController::class);
});
