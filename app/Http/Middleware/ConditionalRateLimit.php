<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ConditionalRateLimit
{
    public function __construct(protected ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        if (! $this->shouldApplyRateLimit()) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $maxAttempts, $decayMinutes);
    }

    /**
     * Determine if rate limiting should be applied based on environment.
     */
    protected function shouldApplyRateLimit(): bool
    {
        $environment = config('app.env');

        return in_array($environment, ['production', 'staging']);
    }
}
