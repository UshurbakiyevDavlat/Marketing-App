<?php

use App\Http\Controllers\CampaignAnalyticsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

//Route::middleware('auth:sanctum')->group(function () { todo return when auth logic will be done
    Route::group(['prefix' => 'v1'], function () {
        Route::group(['prefix' => 'campaigns'], function () {
            Route::get('/', [CampaignController::class, 'index']);
            Route::post('/', [CampaignController::class, 'store']);
            Route::get('/{id}', [CampaignController::class, 'show']);
            Route::put('/{id}', [CampaignController::class, 'update']);
            Route::delete('/{id}', [CampaignController::class, 'destroy']);
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
            Route::put('/{id}', [SubscriberController::class, 'update']);
            Route::delete('/{id}', [SubscriberController::class, 'destroy']);
            Route::post('/import', [SubscriberController::class, 'import']); // Импорт подписчиков через CSV
        });
    });
//});

Route::post('sendgrid/webhook', [WebhookController::class, 'handleWebhook']);
