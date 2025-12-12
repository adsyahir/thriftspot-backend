<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtCookieMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware extracts the JWT access token from the HTTP-only cookie
     * and adds it to the Authorization header for the JWT guard to process.
     *
     * Token refresh is handled explicitly via /auth/refresh endpoint.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If Authorization header is not present, check for access_token in cookie
        if (! $request->bearerToken() && $request->hasCookie('access_token')) {
            $token = $request->cookie('access_token');
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }
}
