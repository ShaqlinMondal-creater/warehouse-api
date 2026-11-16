<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\BidWorkspaceController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\WorkProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return [
        'ok' => true,
        'app' => config('warehouse.app.name'),
        'ai_enabled' => config('warehouse.ai.enabled'),
    ];
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/overview', OverviewController::class);

    Route::apiResource('work-projects', WorkProjectController::class);
    Route::apiResource('bid-workspaces', BidWorkspaceController::class);

    Route::get('bid-workspaces/{bidWorkspace}/bids', [BidController::class, 'index']);
    Route::post('bid-workspaces/{bidWorkspace}/bids', [BidController::class, 'store']);
    Route::get('bid-workspaces/{bidWorkspace}/analytics', [BidController::class, 'analytics']);
    Route::get('bids/{bid}', [BidController::class, 'show']);
    Route::put('bids/{bid}', [BidController::class, 'update']);
    Route::patch('bids/{bid}', [BidController::class, 'update']);
    Route::delete('bids/{bid}', [BidController::class, 'destroy']);

    Route::apiResource('plans', PlanController::class);
    Route::post('plans/{plan}/items', [PlanController::class, 'storeItem']);
    Route::put('plan-items/{planItem}', [PlanController::class, 'updateItem']);
    Route::patch('plan-items/{planItem}', [PlanController::class, 'updateItem']);
    Route::delete('plan-items/{planItem}', [PlanController::class, 'destroyItem']);

    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'upsert']);
});
