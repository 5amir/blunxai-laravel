<?php

use Illuminate\Support\Facades\Route;
use Blunx\AI\Http\Controllers\BlunxApiController;

/*
|--------------------------------------------------------------------------
| Blunx AI — REST API
|--------------------------------------------------------------------------
|
| Authenticated endpoints under /api/blunx. The `blunx` middleware group
| handles the session and auth guard; requests are rate limited (60/min).
*/

Route::middleware(['blunx', 'throttle:60,1'])->prefix('api/blunx')->group(function () {

    Route::get('/user', [BlunxApiController::class, 'user']);

    Route::get('/conversations', [BlunxApiController::class, 'conversations']);
    Route::post('/conversations', [BlunxApiController::class, 'createConversation']);
    Route::delete('/conversations/{uuid}', [BlunxApiController::class, 'deleteConversation']);

    Route::get('/conversations/{convUuid}/messages', [BlunxApiController::class, 'messages']);
    Route::post('/messages', [BlunxApiController::class, 'saveMessage']);
    Route::delete('/messages/{uuid}', [BlunxApiController::class, 'deleteMessage']);

    Route::post('/feedback', [BlunxApiController::class, 'saveFeedback']);

    Route::get('/dashboards', [BlunxApiController::class, 'dashboards']);
    Route::post('/dashboards', [BlunxApiController::class, 'createDashboard']);
    Route::get('/dashboards/{uuid}', [BlunxApiController::class, 'showDashboard']);
    Route::delete('/dashboards/{uuid}', [BlunxApiController::class, 'deleteDashboard']);

    Route::post('/widgets', [BlunxApiController::class, 'saveWidget']);
    Route::patch('/widgets/{widgetUuid}', [BlunxApiController::class, 'updateWidget']);
    Route::delete('/widgets/{widgetUuid}', [BlunxApiController::class, 'deleteWidget']);
    Route::post('/widgets/{widgetUuid}/execute', [BlunxApiController::class, 'executeWidget']);

    Route::get('/insights/{dashUuid}', [BlunxApiController::class, 'insights']);
    Route::get('/insights/{dashUuid}/settings', [BlunxApiController::class, 'getInsightSettings']);
    Route::delete('/insights/{uuid}', [BlunxApiController::class, 'deleteInsight']);
    Route::post('/insights/{uuid}/mark-read', [BlunxApiController::class, 'markInsightRead']);
    Route::get('/insights/{uuid}/download', [BlunxApiController::class, 'downloadReport']);
    Route::post('/insight-settings', [BlunxApiController::class, 'saveInsightSetting']);
    Route::delete('/insights/jobs/{widgetUuid}', [BlunxApiController::class, 'cancelInsightJob']);
    
    Route::post('/execute-sql', [BlunxApiController::class, 'executeSql']);
    Route::post('/stream', [BlunxApiController::class, 'stream']);

});
