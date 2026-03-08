# Design Patterns

This document catalogs all design patterns used throughout the Atlas API application. The project demonstrates 37+ industry-standard patterns organized across Clean Architecture layers.

## Table of Contents

1. [Architectural Patterns](#architectural-patterns)
2. [Structural Patterns](#structural-patterns)
3. [Behavioral Patterns](#behavioral-patterns)
4. [Creational Patterns](#creational-patterns)
5. [Persistence Patterns](#persistence-patterns)
6. [Job & Queue Patterns](#job--queue-patterns)
7. [API Patterns](#api-patterns)
8. [Caching & Performance Patterns](#caching--performance-patterns)
9. [Error Handling Patterns](#error-handling-patterns)
10. [Multi-Tenancy Patterns](#multi-tenancy-patterns)
11. [Presentation Layer Patterns](#presentation-layer-patterns)
12. [Testing Patterns](#testing-patterns)

---

## Architectural Patterns

### Clean Architecture

**Location:** Entire application structure across `app/Core`, `app/Infrastructure`, `app/Presentation`

**Description:** The foundational architectural pattern organizing the codebase into strict dependency layers:
- **Domain** (`app/Core/Domain/`) — Business entities, value objects, domain exceptions
- **Application** (`app/Core/Application/`) — Use cases, contracts/interfaces, DTOs
- **Infrastructure** (`app/Infrastructure/`) — Eloquent models, repositories, external services
- **Presentation** (`app/Presentation/Http/`) — Controllers, form requests, API resources, middleware

**Key Principle:** Inner layers never depend on outer layers. Dependencies always point inward.

**Example:**
```php
// Use case (Application layer) depends on interface (Application layer)
// Never directly on Eloquent model (Infrastructure layer)
class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}
}
```

### Layered Architecture

**Description:** Horizontal separation of concerns with well-defined boundaries between layers, allowing independent development and testing of each layer.

### Hexagonal Architecture (Ports & Adapters)

**Description:** Domain layer acts as the core business logic center. Infrastructure layer provides adapters that translate external system requirements to domain interfaces.

**Example:**
- **Port:** `UserRepositoryInterface` (contract)
- **Adapter:** `EloquentUserRepository` (Eloquent implementation)

---

## Structural Patterns

### Repository Pattern

**Location:** `app/Core/Application/Contracts/` (interfaces) and `app/Infrastructure/Persistence/Eloquent/Repositories/` (implementations)

**Interfaces:**
- `UserRepositoryInterface`
- `TenantRepositoryInterface`
- `UserImportRepositoryInterface`
- `ActivityLogRepositoryInterface`

**Description:** Abstracts data access logic from business logic. Repositories implement domain interfaces, allowing different storage implementations without affecting use cases.

**Benefits:**
- Decouples business logic from data access
- Enables easy switching between storage implementations
- Simplifies testing with mock repositories

**Example:**
```php
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): User;
    public function delete(int $id): bool;
}

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $model = $this->applyTenantScope(UserModel::with('roles'))->find($id);
        return $model !== null ? $this->toEntity($model) : null;
    }
}
```

### Data Transfer Object (DTO) Pattern

**Location:** `app/Core/Application/DTOs/`

**DTO Classes:**
- `AuthUserDTO`
- `ActivityLogEntry`
- `ActivityLogFilters`
- `UserFilters`

**Description:** Immutable objects transfer data between application layers without exposing domain entities. DTOs use PHP 8.1+ `readonly` properties for immutability.

**Benefits:**
- Type-safe data transfer
- Decouples API contracts from internal domain models
- Clear, explicit data shapes

**Example:**
```php
readonly class AuthUserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public DateTimeImmutable $createdAt,
        public ?int $tenantId = null,
    ) {}
}
```

### Adapter Pattern

**Location:** `app/Infrastructure/Persistence/Eloquent/Repositories/`

**Description:** Repository implementations adapt Eloquent models to domain interfaces, allowing the domain layer to remain independent of Laravel framework specifics.

**Example:**
```php
// Eloquent model (Infrastructure)
$userModel = UserModel::find(1);

// Repository adapter converts to domain entity
return new User(
    id: $userModel->id,
    name: $userModel->name,
    email: new Email($userModel->email),
);
```

### Facade Pattern

**Location:** `app/Infrastructure/Tenant/TenantContext.php`

**Description:** `TenantContext` provides a simplified interface to manage tenant state across the application without exposing internal complexity.

**Example:**
```php
class TenantContext
{
    public function set(Tenant $tenant): void { }
    public function get(): ?Tenant { }
    public function getTenantId(): ?int { }
    public function isResolved(): bool { }
}
```

### Proxy Pattern

**Location:** `app/Infrastructure/Persistence/Eloquent/`

**Description:** Eloquent models act as proxies to domain entities. Repositories intercept data access and transform between ORM and domain models.

---

## Behavioral Patterns

### Use Case (Command) Pattern

**Location:** `app/Core/Application/UseCases/`

**Examples:**
- `CreateUserUseCase`
- `LoginUserUseCase`
- `ImportUsersUseCase`
- `ListActivityLogsUseCase`
- `DeleteUserUseCase`
- `ExportUsersUseCase`

**Description:** Each use case encapsulates a single piece of business logic. Controllers delegate to use cases via `execute()` method, receiving Request and Response DTOs.

**Structure:**
```
UseCases/
├── User/
│   ├── CreateUser/
│   │   ├── CreateUserRequest.php (DTO)
│   │   ├── CreateUserResponse.php (DTO)
│   │   └── CreateUserUseCase.php
│   ├── DeleteUser/
│   ├── ImportUsers/
│   └── ...
├── Auth/
├── Tenant/
└── ActivityLog/
```

**Benefits:**
- Single responsibility per use case
- Testable in isolation
- Reusable across different delivery mechanisms (HTTP, CLI, etc.)

### Dependency Injection Pattern

**Location:** `app/Providers/` and throughout the application

**Service Providers:**
- `RepositoryServiceProvider` — Binds interfaces to implementations
- `UseCaseServiceProvider` — Binds service interfaces and singletons
- `AppServiceProvider` — Gates, rate limiting config

**Example:**
```php
// RepositoryServiceProvider
$this->app->bind(
    UserRepositoryInterface::class,
    EloquentUserRepository::class
);

// UseCaseServiceProvider
$this->app->singleton(TenantContext::class, fn () => new TenantContext);
```

**Benefits:**
- Loose coupling between components
- Easy to swap implementations for testing
- Centralized dependency configuration

### Service Locator Pattern

**Description:** Laravel's service container manages dependency resolution and object creation, allowing components to request dependencies by interface.

### Observer Pattern

**Location:** `app/Infrastructure/Persistence/Eloquent/Observers/UserModelObserver.php`

**Description:** `UserModelObserver` listens to Eloquent model lifecycle events and automatically logs activities.

**Observed Events:**
- `created` — Logs when user is created
- `updated` — Logs property changes
- `deleted` — Logs soft deletion
- `restored` — Logs restoration from trash

**Benefits:**
- Decouples audit logging from business logic
- Automatic tracking of all changes
- Transparent to use cases

**Example:**
```php
class UserModelObserver
{
    public function updated(UserModel $user): void
    {
        activity()
            ->performedOn($user)
            ->withProperties(['old' => $changes, 'new' => $user->getChanges()])
            ->log('updated');
    }
}
```

### Strategy Pattern

**Location:** `app/Core/Application/Contracts/UserCreatedNotifierInterface.php` and implementations

**Description:** Multiple notification strategies implement a common interface, allowing swappable notification behavior.

**Implementations:**
- `LaravelUserCreatedNotifier` — Uses Laravel notifications

**Example:**
```php
interface UserCreatedNotifierInterface
{
    public function notifyUserCreated(int $userId, string $userName, string $userEmail): void;
}

// Easy to add new strategies:
class SendgridUserCreatedNotifier implements UserCreatedNotifierInterface { }
class SlackUserCreatedNotifier implements UserCreatedNotifierInterface { }
```

### Chain of Responsibility Pattern

**Location:** `app/Presentation/Http/Middleware/`

**Middleware Stack:**
- `ResolveTenant` — Identifies current tenant
- `AuthorizeTenant` — Validates tenant access
- `AuthorizeUser` — Checks user permissions
- `RateLimitMiddleware` — Enforces rate limits
- `CacheControlMiddleware` — Adds cache headers
- `LogApiRequests` — Logs API activity

**Description:** Request passes through middleware chain, each component handling specific concerns in sequence.

**Benefits:**
- Modular request processing
- Separation of cross-cutting concerns
- Reusable middleware components

### Policy Pattern

**Location:** `app/Presentation/Http/Policies/`

**Policies:**
- `UserPolicy` — User access control
- `TenantPolicy` — Tenant access control
- `ActivityLogPolicy` — Activity log access control

**Description:** Authorization logic encapsulated in policy classes following Laravel's policy convention.

**Example:**
```php
class UserPolicy
{
    public function viewAny(UserModel $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(UserModel $user, UserModel $target): bool
    {
        return $this->isAdmin($user) || $user->getKey() === $target->getKey();
    }
}
```

### Template Method Pattern

**Location:** `app/Exceptions/ApiException.php`

**Description:** Base exception class defines structure; subclasses customize behavior.

**Exception Hierarchy:**
- `ApiException` (base)
  - `ValidationException` (400)
  - `UnauthorizedException` (401)
  - `ForbiddenException` (403)
  - `NotFoundException` (404)
  - `ConflictException` (409)
  - `InternalServerException` (500)
  - `ServiceUnavailableException` (503)

**Benefits:**
- Consistent exception handling
- Automatic HTTP status mapping
- Centralized error response formatting

---

## Creational Patterns

### Factory Pattern

**Location:** `database/factories/`

**Factories:**
- `UserModelFactory`
- `TenantModelFactory`
- `RoleModelFactory`
- `UserImportModelFactory`

**Description:** Factories generate test data and Eloquent models for testing and seeding.

**Example:**
```php
$user = UserFactory::new()->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

### Builder Pattern

**Location:** Throughout the application with Eloquent

**Description:** Eloquent query builder allows constructing complex database queries with method chaining.

**Example:**
```php
UserModel::query()
    ->where('tenant_id', $tenantId)
    ->with('roles')
    ->orderBy('created_at', 'desc')
    ->paginate(15);
```

### Singleton Pattern

**Location:** `app/Providers/UseCaseServiceProvider.php`

**Registered Singletons:**
- `TenantContext` — Maintains current tenant state per request

**Description:** Ensures only one instance of a class exists application-wide.

**Example:**
```php
$this->app->singleton(TenantContext::class, fn () => new TenantContext);
```

**Benefits:**
- Shared state across application
- Guaranteed instance consistency
- Efficient resource usage

---

## Persistence Patterns

### Active Record Pattern

**Location:** `app/Infrastructure/Persistence/Eloquent/Models/`

**Models:**
- `UserModel`
- `TenantModel`
- `UserImportModel`
- `RoleModel`
- `PermissionModel`
- `ActivityLogModel`

**Description:** Eloquent models represent database records and include CRUD operations as instance methods.

**Example:**
```php
$user = UserModel::find(1);
$user->name = 'Updated Name';
$user->save(); // Built-in save method
```

### Value Object Pattern

**Location:** `app/Core/Domain/ValueObjects/Email.php`

**Description:** Immutable objects encapsulating single values with validation logic.

**Example:**
```php
class Email
{
    public function __construct(private readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email: {$value}");
        }
    }

    public function getValue(): string { return $this->value; }
    public function equals(Email $other): bool { return $this->value === $other->value; }
}
```

**Benefits:**
- Validation at construction
- Type safety
- Immutability prevents accidental changes

### Entity Pattern

**Location:** `app/Core/Domain/Entities/`

**Entities:**
- `User` — Core user domain entity
- `Tenant` — Multi-tenant boundary
- `UserImport` — CSV import tracking
- `Role` — Role-based access control

**Description:** Domain entities represent core business concepts with identity, state, and business logic.

**Key Characteristics:**
- Identity (ID) that persists across time
- Mutable state with business logic methods
- Never directly exposed via API (mapped to resources)

**Example:**
```php
class User
{
    public function __construct(
        private ?int $id,
        private string $name,
        private Email $email,
        private ?string $password = null,
        private array $roles = [],
    ) {}

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function hasRole(string $slug): bool { }
    public function isSuperAdmin(): bool { }
}
```

### Enum Pattern

**Location:** `app/Core/Domain/Enums/ImportStatus.php`

**Description:** Type-safe enumerations for domain constants using PHP 8.1+ enums.

**Example:**
```php
enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
```

**Benefits:**
- Type safety over string constants
- IDE autocomplete support
- Exhaustiveness checking in match expressions

---

## Job & Queue Patterns

### Command Queue Pattern

**Location:** `app/Jobs/ProcessImportChunk.php`

**Description:** Asynchronous job processing for long-running operations like CSV imports.

**Configuration:**
- Queue Driver: Database
- Managed by: Horizon
- Retries: 3 attempts
- Timeout: 300 seconds
- Batching: Supports batch operations

**Example:**
```php
class ProcessImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;
    public int $tries = 3;

    public function handle(UserImportRepositoryInterface $importRepository): void
    {
        // Process CSV chunk
    }
}
```

**Benefits:**
- Non-blocking user experience
- Automatic retry on failure
- Progress tracking via Horizon

### Batch Processing Pattern

**Location:** `app/Jobs/ProcessImportChunk.php` with batch queuing

**Description:** Large CSV imports split into chunks and processed via queue batches, enabling parallel processing and progress reporting.

**Flow:**
1. User uploads CSV
2. Import request creates chunks
3. Each chunk dispatched to queue
4. Horizon processes in parallel
5. Progress broadcast via WebSocket
6. User receives real-time updates

### Observer-Observable Pattern (Events)

**Location:** `app/Infrastructure/Broadcasting/Events/`

**Broadcasting Events:**
- `ImportProgressUpdated` — Real-time import progress
- `ImportCompleted` — Import finished successfully

**Description:** Events trigger side effects and broadcast updates to connected clients via Reverb WebSocket.

**Example:**
```php
class ImportProgressUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $importId,
        public readonly int $processedRows,
        public readonly float $progressPercentage,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("imports.{$this->importId}")];
    }
}
```

---

## API Patterns

### Resource Pattern

**Location:** `app/Presentation/Http/Resources/`

**Resources:**
- `UserResource` — User data transformation (v1)
- `V2/UserResource` — User data transformation (v2)
- `TenantResource` — Tenant data transformation
- `ActivityLogResource` — Activity log transformation

**Description:** Eloquent API Resources transform domain entities into API-safe JSON representations.

**Benefits:**
- Separates internal entity structure from API contract
- Consistent data formatting
- Relationship eager loading optimization

**Example:**
```php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => RoleResource::collection($this->roles),
            'created_at' => $this->created_at,
        ];
    }
}
```

### Form Request Validation Pattern

**Location:** `app/Presentation/Http/Requests/`

**Form Requests:**
- `LoginRequest`
- `CreateUserRequest`
- `UpdateTenantRequest`
- `ImportUsersRequest`
- `UploadAvatarRequest`

**Description:** Dedicated classes encapsulate validation rules and custom error messages, keeping controllers clean.

**Benefits:**
- Reusable validation logic
- Custom error messages
- Automatic request validation before controller execution

**Example:**
```php
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
        ];
    }
}
```

### API Versioning Pattern

**Location:** Routes configuration and `V2/` resource subdirectory

**Description:** Multiple API versions supported simultaneously:
- `v1/` — Current API version
- `v2/` — New features or breaking changes in separate resources

**Benefits:**
- Backward compatibility
- Gradual migration path
- Client flexibility

---

## Caching & Performance Patterns

### Cache-Aside (Lazy Loading) Pattern

**Location:** `app/Presentation/Http/Middleware/CacheControlMiddleware.php`

**Description:** Intelligent caching with version-based invalidation using Redis cache keys.

**Configuration:**
```php
private const CACHE_RULES = [
    'api/v1/users/stats'   => ['cacheControl' => 'private, max-age=300', 'versionKey' => 'version:users'],
    'api/v1/users'         => ['cacheControl' => 'private, max-age=60',  'versionKey' => 'version:users'],
    'api/v1/activity-logs' => ['cacheControl' => 'private, max-age=60',  'versionKey' => 'version:activity-logs'],
];
```

**Features:**
- Version-based cache invalidation
- Per-route cache configuration
- Automatic cache header injection
- Redis integration for distributed caching

**Benefits:**
- Reduced database queries
- Faster API responses
- Automatic invalidation on data changes

### Rate Limiting Pattern

**Location:** `app/Presentation/Http/Middleware/RateLimitMiddleware.php`

**Configuration:**
- Limit: 60 requests per minute
- Middleware: `throttle:api`

**Example:**
```php
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->throttle->handle($request, $next, 'api');
    }
}
```

---

## Error Handling Patterns

### Exception Hierarchy Pattern

**Location:** `app/Exceptions/`

**Structure:**
```
ApiException (abstract base)
├── ValidationException (400)
├── UnauthorizedException (401)
├── ForbiddenException (403)
├── NotFoundException (404)
├── ConflictException (409)
├── InternalServerException (500)
└── ServiceUnavailableException (503)
```

**Description:** All exceptions extend `ApiException` base class with consistent HTTP status mapping and error response formatting.

**Example:**
```php
abstract class ApiException extends Exception
{
    public function __construct(
        string $message = '',
        protected int $httpStatusCode = 500,
        protected ?string $errorCode = null,
    ) {}

    public function getHttpStatusCode(): int { return $this->httpStatusCode; }
}

class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Not found')
    {
        parent::__construct($message, httpStatusCode: 404, errorCode: 'NOT_FOUND');
    }
}
```

**Benefits:**
- Consistent error responses
- Automatic HTTP status mapping
- Centralized error handling in exception handler

**Global Handler:** `bootstrap/app.php` renders exceptions as JSON with appropriate status codes.

---

## Multi-Tenancy Patterns

### Tenant Context Pattern

**Location:** `app/Infrastructure/Tenant/TenantContext.php`

**Description:** Singleton managing current tenant state across the request lifecycle.

**Key Methods:**
```php
public function set(Tenant $tenant): void { }
public function get(): ?Tenant { }
public function getTenantId(): ?int { }
public function isResolved(): bool { }
```

**Usage:**
```php
// Middleware resolves tenant
$this->tenantContext->set($tenant);

// Use cases access current tenant
$tenantId = $this->tenantContext->getTenantId();
```

**Benefits:**
- Automatic tenant scoping
- Request-local state
- No manual tenant passing

### Query Scope Pattern

**Location:** `app/Infrastructure/Persistence/Eloquent/Repositories/`

**Description:** Repository methods automatically apply tenant filtering to prevent cross-tenant data leakage.

**Example:**
```php
class EloquentUserRepository
{
    public function findById(int $id): ?User
    {
        // Automatically scoped to current tenant
        $model = $this->applyTenantScope(UserModel::with('roles'))->find($id);
        return $model !== null ? $this->toEntity($model) : null;
    }

    private function applyTenantScope(Builder $query): Builder
    {
        $tenantId = $this->tenantContext->getTenantId();
        return $query->where('tenant_id', $tenantId);
    }
}
```

**Benefits:**
- Transparent tenant isolation
- No accidental cross-tenant queries
- DRY tenant filtering

---

## Presentation Layer Patterns

### Controller Pattern

**Location:** `app/Presentation/Http/Controllers/`

**Description:** Controllers receive HTTP requests, delegate to use cases, and return formatted responses.

**Responsibilities:**
- Route handling
- Request parsing
- Use case invocation
- Response formatting
- Exception handling

**Example:**
```php
class UserController
{
    public function store(
        CreateUserRequest $request,
        CreateUserUseCase $createUserUseCase,
    ) {
        $response = $createUserUseCase->execute(
            new CreateUserRequest($request->validated())
        );

        return new UserResource($response->user);
    }
}
```

### Middleware Pattern

**Location:** `app/Presentation/Http/Middleware/`

**Middleware Types:**

1. **Tenant Resolution** — `ResolveTenant`, `ResolveTenantOptional`
   - Identifies tenant from request subdomain/header
   - Sets tenant context for downstream components

2. **Authorization** — `AuthorizeTenant`, `AuthorizeUser`, `AuthorizeActivityLog`
   - Enforces tenant access control
   - Prevents unauthorized data access

3. **Rate Limiting** — `RateLimitMiddleware`
   - Enforces API rate limits
   - Returns 429 on limit exceeded

4. **Caching** — `CacheControlMiddleware`
   - Adds cache headers
   - Implements version-based invalidation

5. **Logging** — `LogApiRequests`
   - Logs all API requests
   - Tracks metrics and usage

6. **Deprecation** — `DeprecatedApiVersion`
   - Warns clients about deprecated API versions

**Benefits:**
- Modular cross-cutting concerns
- Reusable across multiple routes
- Clean separation from business logic

---

## Testing Patterns

### Test Fixture Pattern

**Location:** `tests/` with PHPUnit

**Features:**
- In-memory SQLite database for isolation
- Array cache driver for testing
- Sync queue for deterministic testing
- Disabled broadcasting/Reverb in test environment

**Test Structure:**
```
tests/
├── Feature/           # Integration tests
├── Unit/              # Unit tests
└── TestCase.php       # Base test class
```

**Example:**
```php
class CreateUserTest extends TestCase
{
    public function testCanCreateUser(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }
}
```

**Configuration:** `phpunit.xml` disables Telescope, Reverb, and broadcasting for test environment.

---

## Summary Statistics

| Category | Count |
|----------|-------|
| Architectural Patterns | 3 |
| Structural Patterns | 5 |
| Behavioral Patterns | 8 |
| Creational Patterns | 3 |
| Persistence Patterns | 4 |
| Job & Queue Patterns | 3 |
| API Patterns | 3 |
| Caching Patterns | 2 |
| Error Handling Patterns | 1 |
| Multi-Tenancy Patterns | 2 |
| Presentation Patterns | 2 |
| Testing Patterns | 1 |
| **Total** | **37+** |

---

## Key Takeaways

1. **Clean Architecture Foundation** — Strict layering with dependency rules enables independent development, testing, and modification
2. **Repository Pattern** — Data access abstraction allows easy switching between storage implementations
3. **Use Cases as Commands** — Each business operation encapsulated as a use case with Request/Response DTOs
4. **Value Objects & Entities** — Rich domain modeling with validation and business logic
5. **Middleware Chain** — Cross-cutting concerns handled elegantly via middleware pipeline
6. **Multi-Tenancy Built-In** — Tenant context and query scopes ensure data isolation
7. **Exception Hierarchy** — Consistent error handling with automatic HTTP status mapping
8. **Event-Driven Updates** — Broadcasting events enable real-time UI updates
9. **Comprehensive Testing** — Fixture pattern with factories and in-memory database
10. **API Versioning** — Multiple API versions supported simultaneously for backward compatibility

This pattern-rich architecture enables:
- **Maintainability** — Clear separation of concerns
- **Testability** — Independent testing of each layer
- **Scalability** — Easy addition of new features
- **Flexibility** — Easy swapping of implementations
- **Reusability** — Components usable across delivery mechanisms