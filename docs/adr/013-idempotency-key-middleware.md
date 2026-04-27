# ADR-013: Idempotency Key Middleware for Mutating Endpoints

## Status

Accepted

## Date

2026-04-27

## Context

Two endpoints are vulnerable to duplicate operations when a client retries after a network timeout or double-submit:

- `POST /api/v1/users/import` — starts an async CSV import job; a duplicate would dispatch redundant batch jobs and create a second `UserImport` record
- `GET /api/v1/users/export` — generates and stores a CSV export file; a duplicate would overwrite or duplicate the file

Both operations are expensive and non-trivially reversible, so we need a guarantee that a client-side retry with the same intent does not produce a second side effect.

The solution must:
- Reject requests that arrive without a key (`422 MISSING_IDEMPOTENCY_KEY`)
- Reject keys outside the allowed character set or length (`422 INVALID_IDEMPOTENCY_KEY`)
- Replay the original response for identical retries, adding an `Idempotency-Replayed: true` header
- Return `409 IDEMPOTENCY_CONFLICT` when the same key is reused with a different request payload
- Scope uniqueness per `(tenant_id, user_id, route_action, key)` — the same key is independent across users and tenants
- Expire records after a configurable TTL (default 24 h)

## Options Considered

### Option A: Validate and cache inside each use case
- Pros: logic close to the operation being protected
- Cons: every future mutating use case must re-implement the same check; use cases are pure business logic and should not own HTTP concerns; the `IdempotencyKey` record is an infrastructure concept, not a domain entity

### Option B: Validate in each controller action
- Pros: easy to reach request headers; one place per endpoint
- Cons: duplicate boilerplate across controllers; controllers should be thin adapters; opt-out by forgetting is easy

### Option C: HTTP middleware, configured by route action name (chosen)
- Pros: runs at the HTTP boundary where it belongs; a single `required_actions` config list controls which routes opt in; controllers and use cases remain unaware; response caching happens at the same layer that produces the response; new endpoints can opt in with one config line
- Cons: the middleware must inspect the response after `$next($request)` and cache it — slightly unusual for Laravel middleware

## Decision

`IdempotencyMiddleware` is registered as a named middleware alias `'idempotency'` in `bootstrap/app.php` and applied to the two routes in `routes/api/v1/users.php`. The middleware reads `config('idempotency.required_actions')` to decide whether to enforce the header on a given request.

### Scope hash instead of composite unique index

The natural unique constraint would be a four-column index on `(tenant_id, user_id, route_action, idempotency_key)`. However, `tenant_id` is nullable (requests from users without a tenant are valid in some contexts), and SQLite — used by the test suite's in-memory database — treats every NULL as distinct in a unique index, breaking the deduplication guarantee in tests.

Instead the middleware computes:

```
scope_hash = SHA-256( tenant_id_or_"null" | user_id | route_action | key )
```

The `idempotency_keys` table has a single `UNIQUE` constraint on the `scope_hash CHAR(64)` column. This is unambiguous, O(1) to look up, and behaves identically across MySQL and SQLite.

### Request hash for conflict detection

To detect a same-key / different-payload conflict, the middleware computes a second hash:

```
request_hash = SHA-256(
  HTTP_METHOD \n
  route_action \n
  file:{param}:{sha256_of_file_bytes}   (one line per uploaded file)
  input:{json_sorted_non_file_input}
)
```

Using `hash_file('sha256', ...)` over the actual uploaded bytes (rather than filename or size) makes the check robust to re-uploads of renamed but identical files, and correctly detects genuinely different payloads.

### Race safety

Between the lookup and the INSERT a concurrent identical request could arrive. The middleware wraps the INSERT in a `try/catch` for `UniqueConstraintViolationException`. On catch it re-reads the record and either replays the completed response or returns `409`, matching the non-racy path exactly.

### Response storage and replay

After `$next($request)` returns, the middleware stores the HTTP status code and decoded JSON body in `response_body` (cast to array on the model). On a duplicate request it reconstructs the response with `response()->json($record->response_body, $record->response_status)` and appends `Idempotency-Replayed: true`.

Only `completed` records are replayed. A record in `processing` state (set on INSERT, before `$next` returns) on a concurrent request is treated as a conflict rather than a replay to avoid returning a 102-style empty response.

## Consequences

- Adding idempotency to a new endpoint requires only one config entry in `config/idempotency.php`; no controller or use-case changes are needed.
- The `idempotency_keys` table grows at one row per unique `(user, action, key)` combination. The `app:prune-idempotency-keys` command (scheduled hourly) deletes rows where `expires_at < now()`.
- Automatic React Query retries on the client send a different `crypto.randomUUID()` key per call (see ADR-008 in the dashboard), so the backend treats each retry as a new operation — consistent with neither import nor export having `retry` configured.
- If a response is not JSON (e.g., a future binary export), the caching strategy must be revisited; the current `response_body` column stores only JSON.
- The `scope_hash` approach means the raw `(tenant_id, user_id, route_action, key)` tuple is not directly queryable for analytics; the SHA-256 is opaque. The raw `idempotency_key` string is stored in a separate non-unique column for debugging purposes.
