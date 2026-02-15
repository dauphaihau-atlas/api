# ThrottleRequests and Laravel RateLimiter

This document describes how `ThrottleRequests` uses Laravel's `RateLimiter` for API rate limiting in this application.

## Overview

The rate limiting stack works as follows:

1. **RateLimitMiddleware** — A thin wrapper that delegates to Laravel's `ThrottleRequests` middleware.
2. **ThrottleRequests** — Laravel's built-in middleware that checks and enforces rate limits.
3. **RateLimiter** — Laravel's rate limiter, which stores counters in the configured cache store (e.g. Redis).

## Flow

```
Request → RateLimitMiddleware → ThrottleRequests → RateLimiter → Cache (Redis)
```

## Components

### RateLimitMiddleware

Location: `app/Presentation/Http/Middleware/RateLimitMiddleware.php`

The middleware wraps `ThrottleRequests` and forwards all handling to it. It uses the named limiter `api` by default:

```php
return $this->throttle->handle($request, $next, $limiter);
```

### ThrottleRequests

- **Package**: `Illuminate\Routing\Middleware\ThrottleRequests`
- **Role**: Receives the request, resolves the named limiter (e.g. `api`), calls `RateLimiter` to check/increment attempts, and returns 429 when the limit is exceeded.

When given a named limiter like `api`, it:

1. Resolves the limiter via `RateLimiter::limiter('api')`
2. Gets the limit configuration (max attempts, decay time, key)
3. Calls `RateLimiter::tooManyAttempts()` to check if throttled
4. Calls `RateLimiter::hit()` on success to increment the counter
5. Returns 429 with `Retry-After` header when throttled

### RateLimiter

- **Package**: `Illuminate\Cache\RateLimiter`
- **Storage**: Uses the application's cache store (configured via `CACHE_STORE` in `.env`)

`RateLimiter` is bound in `CacheServiceProvider`:

```php
return new RateLimiter($app->make('cache')->driver(
    $app['config']->get('cache.limiter')
));
```

When `cache.limiter` is null, it uses the default cache driver. In this app, that is Redis (`CACHE_STORE=redis`).

## Configuration

### Named Limiter (api)

Registered in `app/Providers/AppServiceProvider.php`:

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

- **Limit**: 60 requests per minute
- **Key**: Authenticated user ID, or IP address for unauthenticated requests

### Cache / Redis

- **Default cache store**: `env('CACHE_STORE', 'database')` — in this app, `redis`
- **Redis cache connection**: Uses database `1` by default (`REDIS_CACHE_DB`)
- **Cache prefix**: `laravel-cache-` (from `Str::slug(APP_NAME) . '-cache-'`)

Rate limit keys are stored in Redis with the cache prefix. Keys may be hashed by `ThrottleRequests` for security.

## Inspecting Rate Limit Keys in Redis

Laravel stores cache (including rate limit counters) in Redis database **1** by default. `redis-cli` defaults to database 0.

To list keys:

```bash
redis-cli -n 1 KEYS '*'
```

Or:

```bash
redis-cli
SELECT 1
KEYS *
```

## Middleware Registration

In `bootstrap/app.php`:

```php
$middleware->alias([
    'throttle.api' => RateLimitMiddleware::class,
]);
```

Applied in `routes/api.php`:

```php
Route::prefix('v1')->middleware('throttle.api')->group(function (): void {
    // ...
});
```

## Testing

Use `requests/scripts/rate-limit-test.sh` to trigger rate limiting. It sends 65 POST requests to `/api/v1/login`; the first 60 return 200/422, the rest return 429.
