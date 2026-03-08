# Multi-Tenancy

A production-ready **header-driven multi-tenancy** system that isolates data at the database level while allowing super-admin users to operate across tenant boundaries. Every API request must resolve a tenant context, which is then propagated through use cases, repositories, and observers to ensure data isolation.

---

## Overview

| Concern | Implementation |
|---------|-----------------|
| Tenant resolution | `X-Tenant-ID` header (integer ID or string slug) |
| Middleware | `ResolveTenant` (required) / `ResolveTenantOptional` (conditional) |
| Context storage | `TenantContext` singleton — injected into use cases and observers |
| Data isolation | Foreign key `tenant_id` on `users`, `user_imports`, `activity_log` tables |
| Super-admin access | `super_admin` role can omit header or specify any tenant |
| Authorization | Policies check tenant ownership before allowing access |

---

## Architecture

### Tenant Entity

**Location:** `app/Core/Domain/Entities/Tenant.php`

Immutable value object representing a tenant:

```php
class Tenant
{
    private ?int $id;
    private string $name;
    private string $slug;              // Unique identifier for URI-based routing
    private ?array $settings;          // JSON configuration
    private bool $isActive;            // Tenants can be deactivated
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
}
```

**Methods:**
- `getId()`, `getName()`, `getSlug()`, `getSettings()`, `isActive()`, `getCreatedAt()`, `getUpdatedAt()`
- `updateName()`, `updateSlug()`, `updateSettings()`, `activate()`, `deactivate()`

### TenantContext

**Location:** `app/Infrastructure/Tenant/TenantContext.php`

A singleton service that holds the currently resolved tenant for the request:

```php
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void { ... }
    public function get(): ?Tenant { ... }
    public function isResolved(): bool { ... }
    public function getTenantId(): ?int { ... }
}
```

**Scope:** Request-scoped; a new instance is created per request and injected into controllers, use cases, and observers.

---

## Request Flow

```
┌───────────────────────────────────────────────────────────────────────┐
│ Client: GET /v1/users  with Header: X-Tenant-ID: 5                   │
└───────────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌───────────────────────────────────────────────────────────────────────┐
│ HTTP Middleware Stack (bootstrap/app.php)                             │
│   └─ ResolveTenant or ResolveTenantOptional                           │
└───────────────────────────────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    ▼                         ▼
         ┌──────────────────┐      ┌──────────────────┐
         │ Header found?    │      │ Super admin?     │
         └────────┬─────────┘      └──────────┬───────┘
                  │ YES                       │ YES
                  ▼                           ▼
      ┌──────────────────────┐    ┌────────────────────┐
      │ Lookup by ID or slug │    │ Allow access       │
      │ in TenantRepository  │    │ without tenant     │
      └──────────┬───────────┘    └────────────────────┘
                 │
      ┌──────────┴──────────┐
      ▼                     ▼
  ┌────────┐            ┌─────────┐
  │ Found? │            │ Active? │
  └───┬────┘            └────┬────┘
      │ YES                  │ YES
      │                      ▼
      │              ┌──────────────────┐
      │              │ TenantContext    │
      │              │ .set($tenant)    │
      │              └────────┬─────────┘
      │                       │
      └───────────┬───────────┘
                  ▼
      ┌──────────────────────┐
      │ Continue to next     │
      │ middleware / handler │
      └──────────────────────┘
```

**Error cases:**
- Missing `X-Tenant-ID` (non-super-admin) → `ValidationException` (400)
- Tenant not found (by ID or slug) → `NotFoundException` (404)
- Tenant inactive → `ValidationException` (400)

---

## Middleware

### ResolveTenant (Required)

**Location:** `app/Presentation/Http/Middleware/ResolveTenant.php`

Enforces tenant resolution; throws an exception if the header is missing or invalid.

```php
public function handle(Request $request, Closure $next): Response
{
    $value = $request->header('X-Tenant-ID');

    // Super admins can omit the header
    if ($value === null || $value === '') {
        $user = $request->user();
        if ($user !== null && $user->hasRole('super_admin')) {
            return $next($request);
        }
        throw new ValidationException('X-Tenant-ID header is required.');
    }

    // Lookup tenant by ID or slug
    $tenant = is_numeric($value)
        ? $this->tenantRepository->findById((int) $value)
        : $this->tenantRepository->findBySlug($value);

    if ($tenant === null) {
        throw new NotFoundException('Tenant not found.');
    }

    if (! $tenant->isActive()) {
        throw new ValidationException('Tenant is inactive.');
    }

    $this->tenantContext->set($tenant);
    return $next($request);
}
```

**Usage:** Apply to routes that always require a tenant.

### ResolveTenantOptional (Conditional)

**Location:** `app/Presentation/Http/Middleware/ResolveTenantOptional.php`

Resolves a tenant if the header is present, but does not throw if missing.

```php
public function handle(Request $request, Closure $next): Response
{
    $value = $request->header('X-Tenant-ID');

    if ($value !== null && $value !== '') {
        $tenant = is_numeric($value)
            ? $this->tenantRepository->findById((int) $value)
            : $this->tenantRepository->findBySlug($value);

        if ($tenant === null) {
            throw new NotFoundException('Tenant not found.');
        }

        if (! $tenant->isActive()) {
            throw new ValidationException('Tenant is inactive.');
        }

        $this->tenantContext->set($tenant);
    }

    return $next($request);
}
```

**Usage:** Apply to routes where a tenant is optional (e.g. health checks, public endpoints).

---

## Data Isolation

### Database Schema

Every table that holds tenant-specific data includes a `tenant_id` foreign key:

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    UNIQUE(tenant_id, email)  -- Email unique per tenant
);

CREATE TABLE user_imports (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    batch_id VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    total_rows INT NOT NULL,
    processed_rows INT NOT NULL DEFAULT 0,
    created_count INT NOT NULL DEFAULT 0,
    updated_count INT NOT NULL DEFAULT 0,
    errors JSON NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE activity_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    log_name VARCHAR(255) NOT NULL,
    event VARCHAR(255) NOT NULL,
    subject_type VARCHAR(255) NOT NULL,
    subject_id BIGINT NOT NULL,
    causer_type VARCHAR(255) NULL,
    causer_id BIGINT NULL,
    properties JSON NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);
```

### Query Filtering

All repositories automatically filter by the current tenant:

```php
// EloquentUserRepository::findPaginated()
public function findPaginated(
    int $page,
    int $perPage,
    ?UserFilters $filters = null
): LengthAwarePaginator {
    $query = UserModel::query()
        ->where('tenant_id', $this->tenantContext->getTenantId())
        ->whereNull('deleted_at');
    
    // Apply search, sort, etc.
    return $query->paginate($perPage, ['*'], 'page', $page);
}
```

**Pattern:** Every query that touches a tenant-scoped table starts with:
```php
->where('tenant_id', $this->tenantContext->getTenantId())
```

This ensures no data leaks between tenants, even if authorization checks are bypassed.

---

## Use Cases & Services

### Tenant-Aware Injection

Use cases receive `TenantContext` via constructor injection:

```php
class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(CreateUserRequest $request): CreateUserResponse
    {
        // Tenant context is available throughout the use case
        $tenantId = $this->tenantContext->getTenantId();
        
        $user = new User(
            id: null,
            tenantId: $tenantId,  // Explicitly bind user to current tenant
            name: $request->name,
            email: $request->email,
            password: $request->password,
        );

        return $this->userRepository->save($user);
    }
}
```

### Propagation to Repositories

Repositories access the tenant context to filter queries:

```php
class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function save(User $user): User
    {
        // Ensure the entity has the current tenant ID
        if ($user->getTenantId() === null) {
            $user->setTenantId($this->tenantContext->getTenantId());
        }

        $model = UserModel::updateOrCreate(
            ['id' => $user->getId()],
            [
                'tenant_id' => $user->getTenantId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                // ...
            ]
        );

        return $this->toEntity($model);
    }
}
```

### Activity Logging with Tenant Context

The `UserModelObserver` logs every change and tags it with the current tenant:

```php
class UserModelObserver
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function created(UserModel $model): void
    {
        $this->log($model, 'created');
    }

    private function log(UserModel $model, string $event): void
    {
        ActivityLogModel::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'log_name' => 'user',
            'event' => $event,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getAuthIdentifier(),
            // ...
        ]);
    }
}
```

**Result:** Every activity log entry is automatically scoped to the tenant that triggered the change.

---

## Authorization & Policies

### Tenant Ownership Checks

Policies verify that a resource belongs to the authenticated user's tenant before granting access:

```php
class UserPolicy
{
    public function view(UserModel $user, UserModel $target): bool
    {
        // Users can only view other users in the same tenant
        // (or themselves, regardless of role)
        if ($user->id === $target->id) {
            return true;
        }

        if ($user->tenant_id !== $target->tenant_id) {
            return false;  // Different tenants
        }

        return $user->hasRole('admin');
    }

    public function update(UserModel $user, UserModel $target): bool
    {
        // Same tenant requirement
        if ($user->tenant_id !== $target->tenant_id) {
            return false;
        }

        return $user->hasRole('admin');
    }
}
```

### Super-Admin Cross-Tenant Access

The `super_admin` role bypasses tenant checks:

```php
// In a policy or gate
if ($user->hasRole('super_admin')) {
    return true;  // Super admins can access any resource
}
```

---

## Tenant Management

### Create Tenant

**Use case:** `CreateTenantUseCase`

```php
$useCase = app(CreateTenantUseCase::class);
$response = $useCase->execute(new CreateTenantRequest(
    name: 'Acme Corp',
    slug: 'acme-corp',
    settings: ['theme' => 'dark'],
    isActive: true,
));

// $response->id, $response->name, $response->slug, ...
```

### List Tenants

**Use case:** `ListTenantsUseCase`

```php
$useCase = app(ListTenantsUseCase::class);
$response = $useCase->execute(new ListTenantsRequest(
    page: 1,
    perPage: 15,
));

// $response->tenants (Tenant[])
// $response->total, $response->perPage, $response->currentPage
```

### Get Tenant

**Use case:** `GetTenantUseCase`

```php
$useCase = app(GetTenantUseCase::class);
$response = $useCase->execute(new GetTenantRequest(id: 5));

// $response->id, $response->name, $response->slug, ...
```

### Delete Tenant

**Use case:** `DeleteTenantUseCase`

Cascades to all users, imports, and activity logs for that tenant.

```php
$useCase = app(DeleteTenantUseCase::class);
$response = $useCase->execute(new DeleteTenantRequest(id: 5));

// $response->deleted (bool)
```

---

## Bootstrap & Registration

### Service Provider Bindings

**Location:** `app/Providers/RepositoryServiceProvider.php`

```php
$this->app->bind(
    TenantRepositoryInterface::class,
    EloquentTenantRepository::class
);
```

### Middleware Registration

**Location:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    // Require tenant for all /api/v1 routes
    $middleware->group('api')->append(ResolveTenant::class);
    
    // OR: Optional tenant for public endpoints
    // $middleware->group('api')->append(ResolveTenantOptional::class);
})
```

---

## Testing

### Seeding Test Tenants

```php
// tests/Feature/UserImportApiTest.php

protected function setUp(): void
{
    parent::setUp();

    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'isActive' => true,
    ]);
}

public function test_user_import_respects_tenant_isolation(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->withHeaders([
        'X-Tenant-ID' => $this->tenant->id,
    ])->post('/v1/users/import', [
        'file' => UploadedFile::fake()->csv('users.csv'),
    ]);

    // Verify import is scoped to this tenant
    $import = UserImport::first();
    $this->assertEquals($this->tenant->id, $import->tenant_id);
}
```

### Cross-Tenant Access Prevention

```php
public function test_user_cannot_access_other_tenant_users(): void
{
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $user1 = User::factory()->for($tenant1)->create();
    $user2 = User::factory()->for($tenant2)->create();

    $this->actingAs($user1);

    $response = $this->withHeaders([
        'X-Tenant-ID' => $tenant2->id,
    ])->get("/v1/users/{$user2->id}");

    // User from tenant1 cannot see user from tenant2
    $this->assertEquals(403, $response->status());
}
```

---

## Best Practices

1. **Always inject TenantContext** — Every service that touches tenant-scoped data should receive `TenantContext` via constructor injection.

2. **Filter at the database layer** — Add `->where('tenant_id', ...)` to every query touching tenant-scoped tables. Don't rely on authorization checks alone.

3. **Set tenant ID on entities** — When creating or updating domain entities, explicitly set the tenant ID:
   ```php
   $user = new User(
       tenantId: $this->tenantContext->getTenantId(),
       // ...
   );
   ```

4. **Use foreign key constraints** — The `ON DELETE CASCADE` constraint ensures that deleting a tenant cascades to all its data.

5. **Test cross-tenant isolation** — Write tests that verify users from different tenants cannot access each other's data.

6. **Super-admin role is powerful** — Limit the number of super-admin users and audit their cross-tenant access carefully.

7. **Validate tenant context** — In critical operations (e.g. bulk import), assert that `TenantContext::isResolved()` returns true before proceeding.

---

## API Endpoints

### Tenant Management (Admin-only)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/v1/tenants` | List all tenants (paginated) |
| `POST` | `/v1/tenants` | Create a new tenant |
| `GET` | `/v1/tenants/{id}` | Get a tenant by ID |
| `PUT` | `/v1/tenants/{id}` | Update a tenant |
| `DELETE` | `/v1/tenants/{id}` | Delete a tenant (cascades) |

### Header Requirement

All endpoints under `/v1/users`, `/v1/activity-logs`, etc., require the `X-Tenant-ID` header:

```bash
curl -X GET http://localhost:8000/api/v1/users \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: 5"

# Or use slug:
curl -X GET http://localhost:8000/api/v1/users \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: acme-corp"

# Super-admin can omit the header:
curl -X GET http://localhost:8000/api/v1/tenants \
  -H "Authorization: Bearer {token}"
```

---

## References

- `app/Core/Domain/Entities/Tenant.php` — Tenant entity
- `app/Infrastructure/Tenant/TenantContext.php` — Context singleton
- `app/Presentation/Http/Middleware/ResolveTenant.php` — Required middleware
- `app/Presentation/Http/Middleware/ResolveTenantOptional.php` — Optional middleware
- `app/Core/Application/Contracts/TenantRepositoryInterface.php` — Repository interface
- `app/Infrastructure/Persistence/Eloquent/Repositories/EloquentTenantRepository.php` — Eloquent implementation
- `app/Core/Application/UseCases/Tenant/` — Tenant management use cases
- `tests/Feature/TenantApiTest.php` — Integration tests