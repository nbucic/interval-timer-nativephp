<?php

namespace App\Providers;

use App\Timer\TimerRunner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TimerRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On the first installation (empty programs table) seed the demo HIIT program.
        // The guard inside DatabaseSeeder::run() makes this idempotent.
        // Wrapped in try/catch: during `artisan migrate` the program table
        // does not yet exist when the service provider boots — swallow that
        // gracefully and let the seeder succeed on the next boot.
        try {
            (new DatabaseSeeder)->run();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
