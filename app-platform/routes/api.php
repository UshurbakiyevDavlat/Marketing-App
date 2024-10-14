<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignAnalyticsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::group(['prefix' => 'v1'], function () {
        Route::group(['prefix' => 'campaigns'], function () {
            Route::get('/', [CampaignController::class, 'index']);
            Route::get('/{id}', [CampaignController::class, 'show']);

            Route::middleware(['role:admin'])->group(function () {
                Route::post('/', [CampaignController::class, 'store']);
                Route::put('/{id}', [CampaignController::class, 'update']);
                Route::delete('/{id}', [CampaignController::class, 'destroy']);
                Route::post('/{id}/attach', [CampaignController::class, 'attachSubscribers']);
            });

            Route::post('/ab-test', [CampaignController::class, 'createABTest']);
            Route::post('/{id}/schedule', [CampaignController::class, 'schedule']);
            Route::post('/{id}/send', [CampaignController::class, 'send']);

            Route::group(['prefix' => 'analytics'], function () {
                Route::get('/all', [CampaignAnalyticsController::class, 'getOverallAnalytics']);
                Route::get('/{id}', [CampaignAnalyticsController::class, 'getCampaignAnalytics']);
            });
        });

        Route::group(['prefix' => 'subscribers'], function () {
            Route::get('/', [SubscriberController::class, 'index']);

            Route::middleware(['role:admin'])->group(function () {
                Route::post('/', [SubscriberController::class, 'store']);
                Route::put('/{id}', [SubscriberController::class, 'update']);
                Route::delete('/{id}', [SubscriberController::class, 'destroy']);
            });
            Route::post('/import', [SubscriberController::class, 'import']);
        });
    });

    Route::get('/me', [AuthController::class, 'getAuthUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::post('sendgrid/webhook', [WebhookController::class, 'handleWebhook']);
