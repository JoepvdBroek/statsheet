<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PROTOTYPE (navigation feedback, throwaway): delays every Inertia request by the
 * `prototype_latency` cookie in ms, to feel a weak gym signal locally. Local only.
 */
class PrototypeLatency
{
    public function handle(Request $request, Closure $next): Response
    {
        $latency = min((int) $request->cookie('prototype_latency', '0'), 5000);

        if (app()->isLocal() && $latency > 0 && $request->header('X-Inertia')) {
            usleep($latency * 1000);
        }

        return $next($request);
    }
}
