<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standardize the JSON error shape used by /api/* and any wantsJson() request:
        // { success: false, message: string, errors: object|array }
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->wantsJson());

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if (! $request->is('api/*') && ! $request->wantsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->wantsJson()) {
                return null;
            }

            if ($e instanceof \App\Exceptions\ZohoApiException) {
                return null; // Let its render() method handle it.
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $status = $status >= 400 && $status < 600 ? $status : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Internal server error',
                'errors' => [],
            ], $status);
        });
    })->create();
