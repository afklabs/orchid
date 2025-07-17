<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\MemberStoryRating;
use App\Observers\StoryPerformanceObserver;
use App\Services\PerformanceMetricsService;

/**
 * Performance Service Provider
 * 
 * Registers performance-related services and observers
 */
class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PerformanceMetricsService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register observers
        MemberStoryRating::observe(StoryPerformanceObserver::class);
    }
}

// Add to config/app.php providers array:
// App\Providers\PerformanceServiceProvider::class,