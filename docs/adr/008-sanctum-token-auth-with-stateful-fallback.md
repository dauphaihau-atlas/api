# ADR-008: Sanctum Token Authentication with Stateful (Cookie) Fallback

## Status

Accepted

## Date

2026-04-27

## Context

The API serves two types of clients:
1. **SPA (first-party frontend)** — a browser-based dashboard on a known domain, capable of maintaining an HTTP-only cookie session
2. **API clients / mobile** — token-based consumers that send `Authorization: Bearer <token>` headers

We need an authentication strategy that works for both client types without maintaining two separate auth systems, while keeping credentials secure.

## Options Considered

### Option A: Token-only (no stateful support)
- Pros: simple; stateless; works for all clients
- Cons: browser SPAs must store tokens in `localStorage` or JavaScript-accessible memory — tokens in `localStorage` are vulnerable to XSS; no CSRF protection

### Option B: Session-only (cookie-based)
- Pros: HTTP-only cookies are XSS-safe; CSRF protection via Sanctum's cookie
- Cons: stateless API clients cannot use sessions; requires cookie jar management for non-browser clients

### Option C: Sanctum with `statefulApi()` enabled (chosen)
- Pros: single package handles both token and cookie authentication via the same `auth:sanctum` middleware; SPAs get secure HTTP-only cookies; API clients use bearer tokens; Sanctum's `TransientToken` handles the cookie case transparently
- Cons: the stateful behaviour adds implicit CSRF requirements for cookie-authenticated requests; the XSRF-TOKEN cookie domain can cause cross-subdomain issues

## Decision

`$middleware->statefulApi()` is called in `bootstrap/app.php`, enabling Sanctum's cookie-based session authentication for domains listed in `config/sanctum.php` (`SANCTUM_STATEFUL_DOMAINS`).

All protected routes use `auth:sanctum` middleware — no separate guard for tokens vs. cookies. Sanctum resolves the correct mechanism from the incoming request automatically.

`SanctumAuthService` wraps token lifecycle:
- `createToken(int $userId): string` — creates a `PersonalAccessToken` and returns the plaintext token
- `revokeCurrentToken()` — deletes the `PersonalAccessToken` record; skips deletion if the current token is a `TransientToken` (cookie-based session — has no DB record)
- `loginSession(int $userId)` — calls `Auth::login()` for the cookie session path

**CSRF carve-out:** The broadcasting auth route (`api/v1/broadcasting/auth`) is excluded from CSRF validation because the `XSRF-TOKEN` cookie set on the `api.*` subdomain cannot be read by JavaScript served from the parent domain, making it impossible for the SPA to send the required header. The route is still protected by `auth:sanctum`.

## Consequences

- SPAs on stateful domains get HTTP-only cookies automatically after calling `POST /v1/auth/login` — no token storage needed in JavaScript.
- API clients receive a plaintext bearer token from the same endpoint and use it in `Authorization` headers.
- Logout must check whether the current token is a `PersonalAccessToken` or `TransientToken` before attempting to delete it — see `SanctumAuthService::revokeCurrentToken()`.
- `SANCTUM_STATEFUL_DOMAINS` must include all frontend domains in production; missing a domain silently falls back to token-only auth for that origin.
- Telescope and Reverb are disabled in the test environment (`phpunit.xml`) to prevent broadcasting and authentication side-effects during tests.
