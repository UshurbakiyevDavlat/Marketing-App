<?php

namespace App\Providers;

use App\Services\EmailServiceInterface;
use App\Services\PaymentServiceInterface;
use App\Services\SendGridEmailService;
use App\Services\StripePaymentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmailServiceInterface::class, SendGridEmailService::class);
        $this->app->bind(PaymentServiceInterface::class, StripePaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
