<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces an Accept: application/json header on API requests so that validation
 * failures, auth exceptions, and unhandled errors come back as JSON instead of
 * an HTML redirect to a non-existent /login.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
