<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Mail\MailManager;
use App\Mail\Transport\RotatingSmtpTransport;
use App\Services\SmtpRotationService;
use App\Services\SmtpTransportFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::statement("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

        $this->app->make(MailManager::class)->extend('rotating_smtp', function () {
            return new RotatingSmtpTransport(
                $this->app->make(SmtpRotationService::class),
                $this->app->make(SmtpTransportFactory::class)
            );
        });
    }
}
