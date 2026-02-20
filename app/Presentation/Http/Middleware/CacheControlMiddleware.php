<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheControlMiddleware
{
    /**
     * Exact path => cache config.
     * versionKey: Redis key holding an integer counter bumped on every write.
     * Only GET 200 responses are considered; others are left uncached.
     */
    private const CACHE_RULES = [
        'api/v1/users/stats'   => ['cacheControl' => 'private, max-age=300', 'versionKey' => 'version:users'],
        'api/v1/users'         => ['cacheControl' => 'private, max-age=60',  'versionKey' => 'version:users'],
        'api/v1/activity-logs' => ['cacheControl' => 'private, max-age=60',  'versionKey' => 'version:activity-logs'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Pre-controller: short-circuit with 304 when version token matches client ETag.
        // This skips the controller and DB query entirely.
        if ($request->isMethod('GET')) {
            $path = ltrim((string) $request->path(), '/');
            $rule = self::CACHE_RULES[$path] ?? null;

            if ($rule !== null) {
                $version = Cache::get($rule['versionKey']);

                if ($version !== null) {
                    $etag = '"'.$version.'"';

                    if ($this->requestMatchesEtag($request, $etag)) {
                        return response('', 304)
                            ->withHeaders([
                                'Cache-Control' => $rule['cacheControl'],
                                'ETag'          => $etag,
                                'Vary'          => 'Authorization',
                            ]);
                    }
                }
            }
        }

        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $path = ltrim((string) $request->path(), '/');
        $rule = self::CACHE_RULES[$path] ?? null;
        if ($rule === null) {
            return $response;
        }

        $version = Cache::get($rule['versionKey']);
        if ($version !== null) {
            $etag = '"'.$version.'"';
        } else {
            // Redis unavailable: fall back to body hash so ETag still works.
            $content = $response->getContent();
            $etag = $this->computeEtag($content === false ? '' : $content);
        }

        $response->headers->set('Cache-Control', $rule['cacheControl']);
        $response->headers->set('ETag', $etag);
        $response->headers->set('Vary', 'Authorization');

        return $response;
    }

    /**
     * Strong ETag (quoted). Used as fallback when Redis is unavailable.
     */
    private function computeEtag(string $content): string
    {
        return '"'.md5($content).'"';
    }

    /**
     * True if client sent If-None-Match and it matches our ETag (or *).
     */
    private function requestMatchesEtag(Request $request, string $etag): bool
    {
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch === null || $ifNoneMatch === '') {
            return false;
        }

        $ifNoneMatch = trim($ifNoneMatch);
        if ($ifNoneMatch === '*') {
            return true;
        }

        $clientTags = array_map('trim', explode(',', $ifNoneMatch));
        foreach ($clientTags as $tag) {
            if (strcasecmp($tag, $etag) === 0) {
                return true;
            }
        }

        return false;
    }
}
