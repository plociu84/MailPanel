<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MailServerInfoService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Paginacja w stylu Bootstrap 5 (aplikacja używa Bootstrapa, nie Tailwinda)
        Paginator::useBootstrapFive();

        // Udostępnij $serverInfo wszystkim widokom.
        // Serwis cache'uje dane na 5 minut, więc SSH nie leci przy każdym żądaniu.
        View::composer('*', function ($view) {
            $view->with('serverInfo', app(MailServerInfoService::class)->get());
        });
    }
}