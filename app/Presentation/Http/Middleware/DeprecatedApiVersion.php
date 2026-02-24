<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches HTTP deprecation headers (RFC 8594) to responses for deprecated API versions.
 *
 * Headers added:
 *   Deprecation  – the date this version was officially deprecated
 *   Sunset       – the date this version will be removed
 *   Link         – rel="successor-version" pointing at the v2 equivalent path
 */
class DeprecatedApiVersion
{
    /** Date the current API version was deprecated (RFC 7231 HTTP-date). */
    private const DEPRECATED_SINCE = 'Wed, 01 Jan 2026 00:00:00 GMT';

    /** Date the current API version will be shut down (RFC 7231 HTTP-date). */
    private const SUNSET_DATE = 'Fri, 01 Jan 2027 00:00:00 GMT';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $successorPath = preg_replace('#/v\d+/#', '/v2/', $request->getPathInfo(), 1);

        $response->headers->set('Deprecation', self::DEPRECATED_SINCE);
        $response->headers->set('Sunset', self::SUNSET_DATE);
        $response->headers->set('Link', '<'.$successorPath.'>; rel="successor-version"');

        return $response;
    }
}
