<?php

use App\Http\Controllers\AiModelController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\BidExtractController;
use App\Http\Controllers\BidWorkspaceController;
use App\Http\Controllers\ExamContentImportController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamPracticeController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PublicPortfolioController;
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

Route::post('/login', [AuthController::class, 'login']);

/** Public portfolio for third-party sites (Vercel portfolio, etc.) */
Route::get('/public/portfolio', [PublicPortfolioController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/overview', OverviewController::class);

    Route::get('/portfolio', [PortfolioController::class, 'show']);
    Route::put('/portfolio', [PortfolioController::class, 'update']);
    Route::patch('/portfolio', [PortfolioController::class, 'update']);
    Route::post('/portfolio/avatar', [PortfolioController::class, 'uploadAvatar']);
    Route::delete('/portfolio/avatar', [PortfolioController::class, 'destroyAvatar']);

    Route::post('work-projects/{work_project}/images', [WorkProjectController::class, 'storeImage']);
    Route::put('work-projects/{work_project}/images/reorder', [WorkProjectController::class, 'reorderImages']);
    Route::delete('work-projects/{work_project}/images/{upload}', [WorkProjectController::class, 'destroyImage']);
    Route::apiResource('work-projects', WorkProjectController::class);
    Route::apiResource('bid-workspaces', BidWorkspaceController::class);

    Route::get('bid-workspaces/{bidWorkspace}/bids', [BidController::class, 'index']);
    Route::post('bid-workspaces/{bidWorkspace}/bids', [BidController::class, 'store']);
    Route::post('bid-workspaces/{bidWorkspace}/extract', [BidExtractController::class, 'extract']);
    Route::post('bid-workspaces/{bidWorkspace}/import', [BidExtractController::class, 'import']);
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

    Route::get('ai-models', [AiModelController::class, 'index']);
    Route::post('ai-models', [AiModelController::class, 'store']);
    Route::put('ai-models/{aiModel}', [AiModelController::class, 'update']);
    Route::patch('ai-models/{aiModel}', [AiModelController::class, 'update']);
    Route::delete('ai-models/{aiModel}', [AiModelController::class, 'destroy']);
    Route::post('ai-models/{aiModel}/default', [AiModelController::class, 'setDefault']);

    /** Exams — catalog CRUD in DB; subject packs = zip extract on disk */
    Route::get('exams', [ExamController::class, 'index']);
    Route::post('exams', [ExamController::class, 'store']);
    Route::get('exams/{exam}', [ExamController::class, 'show']);
    Route::put('exams/{exam}', [ExamController::class, 'update']);
    Route::patch('exams/{exam}', [ExamController::class, 'update']);
    Route::delete('exams/{exam}', [ExamController::class, 'destroy']);
    Route::post('exams/{exam}/subjects', [ExamController::class, 'storeSubject']);
    Route::put('exams/{exam}/subjects/{subject}', [ExamController::class, 'updateSubject']);
    Route::patch('exams/{exam}/subjects/{subject}', [ExamController::class, 'updateSubject']);
    Route::delete('exams/{exam}/subjects/{subject}', [ExamController::class, 'destroySubject']);
    Route::get('exams/{exam}/subjects/{subject}', [ExamController::class, 'subject']);
    Route::post('exams/{exam}/subjects/{subject}/sync', [ExamController::class, 'sync']);
    Route::delete('exams/{exam}/subjects/{subject}/pack', [ExamController::class, 'clearPack']);

    /** Separate content-bank import (disk pack → exam_content_*). Does not replace sync/practice. */
    Route::post('exams/{exam}/subjects/{subject}/content/import', [ExamContentImportController::class, 'import']);
    Route::get('exams/{exam}/subjects/{subject}/content/summary', [ExamContentImportController::class, 'summary']);

    Route::get('exams/{exam}/subjects/{subject}/practice/sets', [ExamPracticeController::class, 'sets']);
    Route::get('exams/{exam}/subjects/{subject}/practice/performance', [ExamPracticeController::class, 'performance']);
    Route::get('exams/{exam}/subjects/{subject}/practice/sets/{setKey}', [ExamPracticeController::class, 'showSet']);
    Route::post('exams/{exam}/subjects/{subject}/practice/sets/{setKey}/submit', [ExamPracticeController::class, 'submit']);
});
