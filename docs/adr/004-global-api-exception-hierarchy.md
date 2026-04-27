# ADR-004: Global ApiException Hierarchy for Consistent Error Responses

## Status

Accepted

## Date

2026-04-27

## Context

Without a shared error contract, different controllers and services return errors in different shapes — some as `response()->json(['error' => ...])`, others as Laravel's default exception pages, others via `abort()`. Clients cannot reliably parse error responses.

We need a single, predictable error response format across all endpoints that:
- Maps any application exception to the correct HTTP status code
- Optionally carries a machine-readable `error_code` for client logic
- Optionally carries contextual data (e.g. field-level validation details)
- Requires no per-controller try/catch boilerplate

## Options Considered

### Option A: Manual JSON responses per controller
- Pros: explicit, no abstraction
- Cons: inconsistent shape across controllers; impossible to enforce a contract; no central place to change the format

### Option B: Laravel's default exception handler with `report()`/`render()` overrides
- Pros: uses existing Laravel primitives
- Cons: matching on exception class strings is fragile; no structured error codes

### Option C: `ApiException` base class + global renderer in `bootstrap/app.php` (chosen)
- Pros: throw-anywhere, catch-nowhere; single rendering point; typed hierarchy with HTTP status baked in; optional `error_code` and `context`
- Cons: custom exception hierarchy is non-standard; developers must know to throw `ApiException` subclasses rather than using `abort()`

## Decision

All application errors extend `ApiException`:

```
app/Exceptions/
├── ApiException.php          # abstract base — httpStatusCode, errorCode, context
├── ValidationException.php   # 400
├── UnauthorizedException.php # 401
├── ForbiddenException.php    # 403
├── NotFoundException.php     # 404
├── ConflictException.php     # 409
├── InternalServerException.php # 500
└── ServiceUnavailableException.php # 503
```

The global renderer in `bootstrap/app.php` handles all JSON-expecting requests:

```php
$exceptions->render(function (Throwable $e, $request) {
    if ($e instanceof ApiException) {
        $payload = ['message' => $e->getMessage()];
        if ($e->getErrorCode() !== null) { $payload['error_code'] = $e->getErrorCode(); }
        // ...context merged in
        return response()->json($payload, $e->getHttpStatusCode());
    }
    // Framework exceptions (AuthenticationException, TooManyRequestsHttpException, etc.) mapped here too
});
```

Domain exceptions (`InvalidEmailException`) are caught in the renderer and re-thrown as `ValidationException` to keep domain exceptions free of HTTP concepts.

The error response shape is: `{ message, error_code?, ...context? }`.

## Consequences

- Any code anywhere in the stack can `throw new NotFoundException(...)` and get a correctly shaped 404 — no controller plumbing needed.
- Adding a new error type requires creating a subclass and choosing an HTTP status; the renderer handles the rest.
- The domain `InvalidEmailException` must be explicitly mapped in the renderer — forgetting this mapping would expose a 500 for what is logically a 422.
- Framework-thrown exceptions (`AuthenticationException`, `AuthorizationException`, `TooManyRequestsHttpException`) are handled as special cases in the same renderer to avoid leaking Laravel's default error pages.
