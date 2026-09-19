<?php

use App\Http\Controllers\Admin\WebhookController;
use App\Http\Controllers\Api\ContentStudioIntakeController;
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

// Agent tokens are draft-only. Owner review/publish actions live behind the web admin guard.
Route::prefix('content-studio')->middleware(['auth:sanctum', 'abilities:content-studio:submit', 'throttle:content-studio-intake'])->group(function () {
    Route::post('/campaigns', [ContentStudioIntakeController::class, 'storeCampaign']);
    Route::post('/campaigns/{campaign}/items', [ContentStudioIntakeController::class, 'storeItem']);
    Route::post('/items/{item}/revisions', [ContentStudioIntakeController::class, 'storeRevision']);
    Route::post('/items/{item}/assets', [ContentStudioIntakeController::class, 'storeAsset']);
    Route::get('/items/{item}', [ContentStudioIntakeController::class, 'showStatus']);
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

// Grizzly SMS webhook (unauthenticated — no signature verification from provider)
Route::post('/webhooks/grizzly-sms', [\App\Http\Controllers\GrizzlySmsWebhookController::class, 'handle']);
