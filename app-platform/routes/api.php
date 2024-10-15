<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignAnalyticsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::group(['prefix' => 'v1'], function () {
        Route::group(['prefix' => 'campaigns'], function () {
            Route::get('/', [CampaignController::class, 'index']);
            Route::get('/{id}', [CampaignController::class, 'show']);

            Route::post('/', [CampaignController::class, 'store']);
            Route::put('/{id}', [CampaignController::class, 'update']);
            Route::delete('/{id}', [CampaignController::class, 'destroy']);

            Route::post('/ab-test', [CampaignController::class, 'createABTest']);
            Route::post('/{id}/schedule', [CampaignController::class, 'schedule']);
            Route::post('/{id}/send', [CampaignController::class, 'send']);
            Route::post('/{id}/attach', [CampaignController::class, 'attachSubscribers']);

            Route::group(['prefix' => 'analytics'], function () {
                Route::get('/all', [CampaignAnalyticsController::class, 'getOverallAnalytics']);
                Route::get('/{id}', [CampaignAnalyticsController::class, 'getCampaignAnalytics']);
            });
        });
        Route::group(['prefix' => 'subscribers'], function () {
            Route::get('/', [SubscriberController::class, 'index']);
            Route::post('/', [SubscriberController::class, 'store']);
            Route::post('/import', [SubscriberController::class, 'import']);
            Route::put('/{id}', [SubscriberController::class, 'update']);
            Route::delete('/{id}', [SubscriberController::class, 'destroy']);
        });
        Route::group(['prefix' => 'subscription'], function (){
            Route::get('/active', [SubscriptionController::class, 'getActiveSubscription']);
            Route::get('/history', [SubscriptionController::class, 'getSubscriptionHistory']);
            Route::post('/create', [SubscriptionController::class, 'createSubscription']);
            Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription']);
            Route::post('/refund', [SubscriptionController::class, 'refund']);
        });
    });

    Route::get('/me', [AuthController::class, 'getAuthUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::post('sendgrid/webhook', [WebhookController::class, 'handleWebhook']);
