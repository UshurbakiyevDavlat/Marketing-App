<?php

namespace App\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use App\Mail\Transport\SendgridTransport;
use GuzzleHttp\Client;
class SendgridServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->extend('mail.manager', function (MailManager $mailManager) {
            $mailManager->extend('sendgrid', function (array $config) {
                $apiKey = $config['api_key'] ?? config('services.sendgrid.api_key');

                return new SendgridTransport(new Client(), $apiKey);
            });

            return $mailManager;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
