<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

$errorViewForStatus = static function (int $status): string {
    if (view()->exists("errors.{$status}")) {
        return "errors.{$status}";
    }

    return $status >= 500 ? 'errors.5xx' : 'errors.4xx';
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecureHeaders::class,
            \App\Http\Middleware\EnsureCurrentTeam::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) use ($errorViewForStatus): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) use ($errorViewForStatus) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status < 400) {
                return $response;
            }

            if ($request->header('X-Inertia') && $request->isMethod('GET')) {
                return Inertia::location($request->fullUrl());
            }

            $viewResponse = response()->view(
                $errorViewForStatus($status),
                ['status' => $status],
                $status
            );

            $viewResponse->headers->replace($response->headers->allPreserveCaseWithoutCookies());

            return $viewResponse;
        });
    })->create();
