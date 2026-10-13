<?php

namespace App\Providers;

use App\Services\Fail2ban\Fail2banSshClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Fail2banSshClient::class, function () {
            return new Fail2banSshClient();
        });
    }

    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();
    }
}