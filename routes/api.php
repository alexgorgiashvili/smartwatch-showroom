<?php

use App\Http\Controllers\Admin\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Webhook routes are unguarded as they are protected by signature verification
| instead of authentication middleware.
|
*/
Route::middleware('webhook.verify')->group(function () {
    Route::post('/webhooks/messages', [WebhookController::class, 'handle']);
});

// Meta webhook verification GET endpoint (unguarded)
Route::get('/webhooks/messages', [WebhookController::class, 'verify']);

/*
|--------------------------------------------------------------------------
| Conversation API Routes (Protected)
|--------------------------------------------------------------------------
|
| Real-time inbox API endpoints - require authentication
|
*/
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('conversations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ConversationController::class, 'index']);
        Route::get('{id}', [\App\Http\Controllers\Api\ConversationController::class, 'show']);
        Route::post('{id}/messages', [\App\Http\Controllers\Api\ConversationController::class, 'sendMessage']);
        Route::post('{id}/read', [\App\Http\Controllers\Api\ConversationController::class, 'markAsRead']);
        Route::post('{id}/status', [\App\Http\Controllers\Api\ConversationController::class, 'updateStatus']);
        Route::post('{id}/toggle-ai', [\App\Http\Controllers\Api\ConversationController::class, 'toggleAi']);
        Route::post('{id}/ai-suggest', [\App\Http\Controllers\Api\ConversationController::class, 'aiSuggestion']);
    });
});
