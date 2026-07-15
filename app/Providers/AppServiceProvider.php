<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Public funnel forms run live (Precognition) validation: one request
        // per field interaction. Those must never starve the real submission,
        // so the two get separate buckets — a generous one for live checks,
        // a strict one for actual submits.
        RateLimiter::for('funnel', function (Request $request) {
            return $request->isPrecognitive()
                ? Limit::perMinute(60)->by('funnel-live:'.$request->ip())
                : Limit::perMinute(10)->by('funnel-submit:'.$request->ip());
        });
    }
}
