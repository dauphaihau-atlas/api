# ADR-009: Gates and Policies for Authorization

## Status

Accepted

## Date

2026-04-27

## Context

The API needs per-route authorization (can this user call this endpoint?) and per-resource authorization (can this user act on this specific record?). Authorization checks could be placed in middleware, controllers, use cases, or as dedicated policy objects.

The approach must:
- Keep controllers and use cases free of role-checking conditionals
- Allow different logic for model-level actions (e.g. a user can view their own profile but not others')
- Be testable independently from the HTTP layer

## Options Considered

### Option A: Role checks in middleware (e.g. `role:admin`)
- Pros: keeps controllers clean; highly visible on routes
- Cons: coarse-grained — cannot express "admin OR owner of this resource"; role names scattered across route files; changing a role name requires hunting route definitions

### Option B: Role/permission checks inside use cases
- Pros: all logic in one place
- Cons: use cases start depending on auth state; violates the Application layer's framework-agnosticism; makes use cases harder to call from non-HTTP contexts (CLI, jobs)

### Option C: Gates for coarse-grained roles + Policies for model-level actions (chosen)
- Pros: Laravel-idiomatic; centralized in `AppServiceProvider`; policies co-locate all abilities for a resource in one class; middleware delegates to the gate/policy without containing logic
- Cons: policies live in `Presentation/Http/Policies/` (a presentation concern), yet they operate on `UserModel` (an infrastructure concern) — a layer impurity accepted for pragmatism

## Decision

**Gates** handle cross-cutting role checks with no specific model instance:

```php
// AppServiceProvider
Gate::define('admin', fn ($user) => $user->hasRole('admin') || $user->hasRole('super_admin'));
Gate::define('super_admin', fn ($user) => $user->hasRole('super_admin'));
```

**Policies** handle per-model authorization and are registered via `Gate::policy()`:

```php
Gate::policy(UserModel::class, UserPolicy::class);
Gate::policy(TenantModel::class, TenantPolicy::class);
Gate::policy(ActivityLogModel::class, ActivityLogPolicy::class);
```

Each policy method returns a `bool` and receives the acting user plus, for instance-level checks, the target model:

```php
// UserPolicy
public function view(UserModel $user, UserModel $target): bool
{
    return $this->isAdmin($user) || $user->getKey() === $target->getKey();
}
```

Routes use thin middleware aliases to invoke the policy without leaking logic into route files:

```php
// AuthorizeUser middleware
Gate::authorize($ability, UserModel::class);

// Route definition
Route::get('users', [UserController::class, 'index'])->middleware('authorize.user:viewAny');
Route::post('users/{user}/avatar', ...)->middleware('can:update,user'); // instance-level
```

The `authorize.user:viewAny` pattern passes the ability name as a middleware parameter, keeping the route file readable while the logic stays in the policy.

## Consequences

- All authorization rules for a resource are in one policy file — adding or changing a rule has a single touch point.
- `AuthorizationException` (thrown by `Gate::authorize()`) and `AccessDeniedHttpException` are both caught in the global exception renderer and converted to `ForbiddenException` (403), so the client always receives the same error shape.
- The `can:update,user` middleware on the avatar route uses Laravel's built-in `can` middleware with model binding — distinct from the custom `authorize.user` middleware used for non-instance checks. Both patterns are in use; choose based on whether a model instance is available.
- Policies currently reference `UserModel` directly rather than a domain entity. This is a deliberate pragmatic choice — using domain entities in policies would require an extra repository call or entity-to-model mapping that adds no security value.
