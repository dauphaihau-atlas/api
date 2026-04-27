# ADR-005: Multi-Tenancy via TenantContext Singleton and Repository Auto-Scoping

## Status

Accepted

## Date

2026-04-27

## Context

The application serves multiple tenants from a single database using a shared-table strategy (`tenant_id` column on `users` and related tables). Every query against tenant-owned data must be scoped to the current tenant to prevent data leakage between tenants.

We need a mechanism to:
- Resolve the current tenant once per request (not per query)
- Automatically apply the tenant scope to all repository queries without forcing every use case to pass a tenant ID explicitly
- Fail loudly when a request is missing the tenant context rather than silently returning all-tenant data

## Options Considered

### Option A: Pass `tenantId` as an explicit parameter through every use case and repository method
- Pros: fully explicit; no hidden state
- Cons: significant noise on every method signature; easy to miss when adding new query methods

### Option B: Eloquent global scope on models
- Pros: automatic; no method changes needed
- Cons: scopes are silently bypassed with `withoutGlobalScope()`; harder to unit-test; scope is applied at the model layer, not the repository layer, breaking the architecture boundary

### Option C: Request-scoped `TenantContext` singleton + repository-level `applyTenantScope()` (chosen)
- Pros: tenant resolved once in middleware and available everywhere in the request; repositories apply the scope consistently; super_admin bypass is centralized in middleware
- Cons: implicit — a developer reading a repository method must know the scope is applied; no compile-time guarantee that context is set before a query runs

## Decision

`TenantContext` is a singleton registered in `UseCaseServiceProvider`:

```php
$this->app->singleton(TenantContext::class, fn () => new TenantContext);
```

`ResolveTenant` middleware runs on all tenant-scoped routes. It reads the `X-Tenant-ID` header (accepts both numeric ID and slug), validates the tenant is active, and calls `TenantContext::set()`. Super admins may omit the header — they operate across all tenants.

Repositories receive `TenantContext` via constructor injection and call `applyTenantScope()` on every query builder before executing:

```php
private function applyTenantScope(Builder $query): Builder
{
    $tenantId = $this->tenantContext->getTenantId();
    if ($tenantId !== null) {
        $query->where('users.tenant_id', $tenantId);
    }
    return $query;
}
```

The null-check allows super_admin requests (no tenant in context) to query across all tenants.

`ResolveTenantOptional` middleware is used on routes where tenant context is useful but not required (e.g. login — the user may belong to any tenant).

## Consequences

- Any new repository method must call `applyTenantScope()` or risk a tenant boundary violation — this is not enforced by the type system; code review is the only guard.
- Use cases are free of `tenantId` parameters, keeping signatures clean.
- Background jobs receive `tenantId` explicitly as a constructor argument (e.g. `ProcessImportChunk`) because the request-scoped singleton is not available in queue workers. Jobs must reconstruct tenant scope from this value.
- Testing tenant isolation requires setting up the singleton or injecting a stubbed `TenantContext` — documented in the test base class setup.
