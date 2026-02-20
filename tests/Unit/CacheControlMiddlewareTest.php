<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Presentation\Http\Middleware\CacheControlMiddleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheControlMiddlewareTest extends TestCase
{
    private CacheControlMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->middleware = new CacheControlMiddleware();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getRequest(string $path, array $headers = []): Request
    {
        $request = Request::create('http://localhost/'.$path, 'GET');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private function next(int $status = 200, string $body = '{"data":[]}'): Closure
    {
        return fn () => response($body, $status);
    }

    private function nextSpy(int $status = 200): array
    {
        $ran = ['called' => false];
        $ran['closure'] = function () use ($status, &$ran) {
            $ran['called'] = true;

            return response('{"data":[]}', $status);
        };

        return $ran;
    }

    // -------------------------------------------------------------------------
    // Pre-controller 304 short-circuit
    // -------------------------------------------------------------------------

    public function test_returns_304_without_running_controller_when_etag_matches(): void
    {
        Cache::put('version:users', 5);
        $spy = $this->nextSpy();

        $request = $this->getRequest('api/v1/users', ['If-None-Match' => '"5"']);
        $response = $this->middleware->handle($request, $spy['closure']);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertFalse($spy['called'], 'Controller must not run on cache hit');
        $this->assertSame('"5"', $response->headers->get('ETag'));
        $this->assertSame('max-age=60, private', $response->headers->get('Cache-Control'));
        $this->assertSame('Authorization', $response->headers->get('Vary'));
    }

    public function test_stats_path_returns_304_with_300s_max_age(): void
    {
        Cache::put('version:users', 2);

        $request = $this->getRequest('api/v1/users/stats', ['If-None-Match' => '"2"']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('max-age=300, private', $response->headers->get('Cache-Control'));
    }

    public function test_activity_logs_path_uses_its_own_version_key(): void
    {
        Cache::put('version:activity-logs', 7);

        $request = $this->getRequest('api/v1/activity-logs', ['If-None-Match' => '"7"']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('"7"', $response->headers->get('ETag'));
    }

    public function test_wildcard_if_none_match_returns_304(): void
    {
        Cache::put('version:users', 1);

        $request = $this->getRequest('api/v1/users', ['If-None-Match' => '*']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(304, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // 200 responses — ETag set from version token
    // -------------------------------------------------------------------------

    public function test_sets_version_etag_on_200_when_no_if_none_match(): void
    {
        Cache::put('version:users', 3);

        $request = $this->getRequest('api/v1/users');
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('"3"', $response->headers->get('ETag'));
        $this->assertSame('max-age=60, private', $response->headers->get('Cache-Control'));
        $this->assertSame('Authorization', $response->headers->get('Vary'));
    }

    public function test_returns_200_when_etag_does_not_match_current_version(): void
    {
        Cache::put('version:users', 9);

        $request = $this->getRequest('api/v1/users', ['If-None-Match' => '"8"']);
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('"9"', $response->headers->get('ETag'));
    }

    // -------------------------------------------------------------------------
    // Fallback to body-hash ETag when version token is absent (Redis down)
    // -------------------------------------------------------------------------

    public function test_falls_back_to_body_hash_etag_when_version_token_missing(): void
    {
        // version:users not in cache — simulates Redis unavailability
        $body = '{"data":[]}';

        $request = $this->getRequest('api/v1/users');
        $response = $this->middleware->handle($request, $this->next(200, $body));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('"'.md5($body).'"', $response->headers->get('ETag'));
        $this->assertSame('max-age=60, private', $response->headers->get('Cache-Control'));
    }

    public function test_no_304_short_circuit_when_version_token_missing(): void
    {
        // Even if client sends a matching-looking ETag, we cannot confirm it
        // without the server-side token — controller must run.
        $called = false;
        $next = function () use (&$called) {
            $called = true;

            return response('{"data":[]}', 200);
        };

        $request = $this->getRequest('api/v1/users', ['If-None-Match' => '"1"']);
        $this->middleware->handle($request, $next);

        $this->assertTrue($called, 'Controller must run when version token is absent');
    }

    // -------------------------------------------------------------------------
    // Pass-through cases — no cache headers added
    // -------------------------------------------------------------------------

    public function test_non_get_request_passes_through_without_cache_headers(): void
    {
        Cache::put('version:users', 1);

        $request = Request::create('http://localhost/api/v1/users', 'POST');
        $response = $this->middleware->handle($request, $this->next(201, '{}'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertNull($response->headers->get('ETag'));
        $this->assertNull($response->headers->get('Vary'));
    }

    public function test_non_200_get_response_passes_through_without_cache_headers(): void
    {
        Cache::put('version:users', 1);

        $request = $this->getRequest('api/v1/users');
        $response = $this->middleware->handle($request, $this->next(404, '{"message":"Not found"}'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertNull($response->headers->get('ETag'));
        // Cache-Control is not set by our middleware (Laravel may add its own default)
        $this->assertNotSame('max-age=60, private', $response->headers->get('Cache-Control'));
    }

    public function test_unlisted_path_passes_through_without_cache_headers(): void
    {
        $request = $this->getRequest('api/v1/some-other-endpoint');
        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('ETag'));
        // Cache-Control is not set by our middleware (Laravel may add its own default)
        $this->assertNotSame('max-age=60, private', $response->headers->get('Cache-Control'));
    }
}
