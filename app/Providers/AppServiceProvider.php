<?php

namespace App\Providers;

use App\Core\ProgressiveBatch;
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
        $this->app->singleton('progressive_batch', function () {
            return new ProgressiveBatch();
        });
    }
}
