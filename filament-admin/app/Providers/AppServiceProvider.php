<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        $memoryLimit = trim((string) env('KDINI_PHP_MEMORY_LIMIT', '512M'));

        if ($memoryLimit !== '') {
            @ini_set('memory_limit', $memoryLimit);
        }
    }
}
