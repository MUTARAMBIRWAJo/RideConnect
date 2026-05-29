<?php

use App\Exceptions\Handlers\DomainExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Register the role middleware
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'auth.partial' => \App\Http\Middleware\PartialAuth::class,
            'mobile' => \App\Http\Middleware\EnsureMobileUser::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\EnforceDomainPolicies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\EnforceDomainPolicies::class,
        ]);

        // Force unauthenticated web redirects to the unified login page
        $middleware->redirectGuestsTo(fn () => route('auth.login'));

        // Allow session-authenticated first-party requests to pass auth:sanctum.
        $middleware->statefulApi();

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        DomainExceptionHandler::register($exceptions);

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e) {
            // If the request expects JSON, return JSON response
            if ($e->isMethod('post') && $e->headers->get('Accept') == 'application/json') {
                return response()->json(['message' => 'Page expired. Please log in again.'], 419);
            }

            // For web requests, redirect to login with a message
            return redirect()->route('auth.login')
                ->with('error', 'Your session has expired. Please log in again to continue.');
        });
    })->create();
