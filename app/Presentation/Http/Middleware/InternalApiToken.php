<?php

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.go_worker.internal_token');
        $providedToken = (string) $request->header('X-Internal-Token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
