<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->prepend(\App\Http\Middleware\Cors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'غير مصرح - يرجى تسجيل الدخول'
                ], 401);
            }
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'غير مصرح - يرجى تسجيل الدخول'
            ], 401);
        });
    })->create();
