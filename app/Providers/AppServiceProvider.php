<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        $this->configureRateLimiting();
        Vite::prefetch(concurrency: 3);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('prompt-quick-test', fn (Request $request) => [
            $this->limit('prompt-quick-test', $request, 12),
        ]);

        RateLimiter::for('experiment-store', fn (Request $request) => [
            $this->limit('experiment-store', $request, 8),
        ]);

        RateLimiter::for('prompt-optimization-store', fn (Request $request) => [
            $this->limit('prompt-optimization-store', $request, 4),
        ]);

        RateLimiter::for('llm-connection-validation', fn (Request $request) => [
            $this->limit('llm-connection-validation', $request, 10),
        ]);
    }

    private function limit(string $name, Request $request, int $maxAttempts): Limit
    {
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        $teamId = $request->user()?->current_team_id ?? 'none';

        return Limit::perMinute($maxAttempts)
            ->by("{$name}:{$userId}:{$teamId}:{$request->ip()}")
            ->response(function () use ($maxAttempts, $name) {
                return response()->json([
                    'message' => 'Too many requests.',
                    'errors' => [
                        'rate_limit' => ["The {$name} limit of {$maxAttempts} requests per minute was exceeded."],
                    ],
                ], 429);
            });
    }
}
