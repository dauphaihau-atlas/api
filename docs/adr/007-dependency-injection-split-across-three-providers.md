# ADR-007: Dependency Injection Split Across Three Service Providers

## Status

Accepted

## Date

2026-04-27

## Context

Laravel's `AppServiceProvider` is the default home for all container bindings. As the application grows with multiple interfaces (repositories, auth, email, notifiers) and cross-cutting concerns (gates, rate limiters, observers), a single provider becomes a long, mixed-concern file that's hard to navigate and reason about.

## Options Considered

### Option A: All bindings in `AppServiceProvider`
- Pros: one file to check
- Cons: `AppServiceProvider` grows into an unmaintainable wall of unrelated bindings

### Option B: Auto-discovery via naming convention (no explicit bindings)
- Pros: no provider maintenance
- Cons: Laravel does not auto-bind interfaces to implementations; convention-based containers (like in other frameworks) are not available here without a third-party package

### Option C: Split by concern across dedicated providers (chosen)
- Pros: each provider has a single clear purpose; easy to find where a binding lives; new interfaces go to the obvious provider
- Cons: three files to check instead of one when tracing a dependency

## Decision

Bindings are split across three providers registered in `bootstrap/providers.php`:

| Provider | Bindings |
|---|---|
| `RepositoryServiceProvider` | All `*RepositoryInterface` → `Eloquent*Repository` bindings |
| `UseCaseServiceProvider` | `TenantContext` singleton; `EmailServiceInterface`, `AuthServiceInterface`, `UserCreatedNotifierInterface` → concrete implementations |
| `AppServiceProvider` | Gates (`admin`, `super_admin`); Policies; Rate limiters; Model observers |

**Rule for new bindings:**
- Repository interface → `RepositoryServiceProvider`
- Service/infrastructure interface used by use cases → `UseCaseServiceProvider`
- App-wide boot configuration (gates, rate limits, observers) → `AppServiceProvider`

## Consequences

- Finding where `UserRepositoryInterface` resolves requires knowing to look in `RepositoryServiceProvider`, not `AppServiceProvider` — the naming makes this predictable.
- `TenantContext` is a singleton registered in `UseCaseServiceProvider` (not `AppServiceProvider`) because it is a dependency of use cases and repositories, not an app-level configuration concern. This placement is worth noting for new contributors.
- Adding a new external service (e.g. `StorageServiceInterface`) means creating the interface in `Application/`, the implementation in `Infrastructure/`, and a binding in `UseCaseServiceProvider`.
