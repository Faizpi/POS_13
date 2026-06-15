<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
            'customer.auth' => \App\Http\Middleware\CustomerAuth::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CustomerSubdomainRedirect::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            // AuthenticationException → redirect to login
            if ($e instanceof AuthenticationException) {
                $redirectTo = $e->redirectTo($request);

                if ($redirectTo) {
                    return redirect()->guest($redirectTo);
                }

                // Fallback berdasarkan guard
                $guard = $e->guards()[0] ?? null;
                return redirect()->guest(match ($guard) {
                    'customer' => '/customer/login',
                    default => '/app/login',
                });
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return; // Bypass — pakai JSON response bawaan
            }

            $statusCode = 500;
            if ($e instanceof HttpExceptionInterface) {
                $statusCode = $e->getStatusCode();
            }

            $viewPath = "errors.{$statusCode}";

            if (view()->exists($viewPath)) {
                return response()->view($viewPath, [
                    'exception' => $e,
                ], $statusCode);
            }

            // Fallback ke 500 jika view spesifik tidak ada
            if (view()->exists('errors.500')) {
                return response()->view('errors.500', [
                    'exception' => $e,
                ], $statusCode);
            }

            return; // Biarkan default Laravel
        });
    })->create();
