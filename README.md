# Atlas API

A production-ready **API-only backend** built with Laravel 12, following Clean Architecture principles. Features role-based access, async CSV import/export, real-time WebSocket broadcasting, Redis caching with ETag support, and comprehensive activity logging.

---

## Applied Techniques

**Architecture**
- **Clean Architecture** — Domain, Application, Infrastructure, and Presentation layers with strict inward-only dependencies
- **Structured Error Handling** — Custom `ApiException` hierarchy maps every failure to a consistent `{ message, error_code, context }` JSON envelope

**Security**
- **API Token Authentication** — Sanctum tokens with per-request revocation on logout
- **Multi-Tenancy** — Header-driven tenant resolution (`X-Tenant-ID` by ID or slug) via `ResolveTenant` / `ResolveTenantOptional` middleware; super-admin users can operate across tenants; tenant context injected into use cases for data isolation and activity logging
- **Role-Based Authorization (RBAC)** — Roles and permissions stored in dedicated tables with many-to-many pivots. Gate + Policy classes consume `UserModel::hasRole()` / `hasPermission()` to guard all privileged endpoints; 12 permissions seeded across `users.*` and `activity-logs.*` groups
- **Rate Limiting** — Three tiers: standard API (60/min), login (5/min), heavy operations like import/export (10/min)
- **Signed URLs** — Export download links are time-limited (15-min expiry) and verified via Laravel's `signed` middleware

**Caching**
- **Response Caching with ETag** — Redis version tokens drive `304 Not Modified` short-circuits; falls back to MD5 body hash
- **Cache-Aside (Query Caching)** — Tagged Redis cache wraps expensive DB queries (e.g. user stats) via `Cache::tags()->remember()`; tags are invalidated on every entity write so stale data is never served

**Async & Real-Time**
- **Queue, Jobs & Workers** — Async CSV import dispatched as batched jobs via Horizon, with retry and timeout handling
- **Real-Time Broadcasting** — Import progress streamed over private WebSocket channels via Laravel Reverb
- **Notifications** — `UserCreatedNotification` dispatched on user creation via a `UserCreatedNotifierInterface` contract

**Data Management**
- **Soft Deletes** — Trash / restore / force-delete lifecycle on users with filter support
- **Full-Text Search** — PostgreSQL `tsquery` with GIN indexes on users; `LIKE` fallback for other databases
- **File Storage** — Avatar upload, CSV import ingestion, and export generation; configurable between local disk and MinIO (S3-compatible)

**Observability**
- **Activity Log** — Model observers capture every user write (created, updated, deleted, restored, force-deleted), recording subject, causer, and changed attributes
- **Structured Request Logging** — Every API request is logged with `X-Request-Id` tracing, route name, duration (ms), user ID, and IP; log level scales with status code (info / warning / error); sensitive headers stripped before logging
- **Built-in Web Dashboards** — Horizon for queue monitoring, Telescope for request/query/exception inspection, Scribe for auto-generated API documentation

| Dashboard | URL | Description |
|---|---|---|
| Horizon | `https://api.atlas.local/horizon` | Queue monitoring and job metrics |
| Telescope | `https://api.atlas.local/telescope` | Request, query, and exception inspection |
| API Docs | `https://api.atlas.local/docs` | Auto-generated API documentation (Scribe) |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Auth | Laravel Sanctum (token-based) |
| Queue | Database driver + Laravel Horizon |
| WebSockets | Laravel Reverb |
| Cache | Redis + ETag version tokens |
| Storage | Local or MinIO (S3-compatible) |
| Debug | Laravel Telescope + Pail |
| Docs | Scribe (API documentation) |
| Testing | PHPUnit 11 (SQLite in-memory) |
| Code Style | Laravel Pint |

---

## Architecture

Clean Architecture with four strict layers — inner layers never depend on outer:

```
app/
├── Core/
│   ├── Domain/          # Entities, Value Objects, domain exceptions
│   └── Application/     # Use cases, contracts (interfaces), DTOs
├── Infrastructure/      # Eloquent models/repos, auth, broadcasting, notifications
└── Presentation/
    └── Http/            # Controllers, form requests, API resources, middleware
```

### Dependency Injection

| Provider | Responsibility |
|---|---|
| `RepositoryServiceProvider` | Repository interfaces → Eloquent implementations |
| `UseCaseServiceProvider` | Service interfaces → concrete implementations |
| `AppServiceProvider` | Gates (`admin` role via RBAC), rate limiter config |

---

## Design Patterns Applied

### GoF Patterns

| Pattern | Type | Where Used |
|---|---|---|
| **Repository** | Structural | `EloquentUserRepository`, `EloquentActivityLogRepository`, `EloquentUserImportRepository` abstract all data access behind interfaces |
| **Observer** | Behavioral | `UserModelObserver` hooks into Eloquent lifecycle events (created, updated, deleted, restored, forceDeleted) to write activity logs and invalidate cache |
| **Strategy** | Behavioral | `EmailServiceInterface`, `AuthServiceInterface`, `UserCreatedNotifierInterface` — swap implementations without touching call sites |
| **Adapter** | Structural | Repository `toEntity()` methods bridge Eloquent models to domain entities, keeping the domain layer ORM-agnostic |
| **Chain of Responsibility** | Behavioral | Middleware stack — `LogApiRequests` → `CacheControlMiddleware` → rate limiters → authorization gates — each link handles or passes the request |
| **Command** | Behavioral | `ProcessImportChunk` encapsulates a CSV chunk job with retries, timeout, and batch callbacks; dispatched to the queue |
| **Builder** | Creational | `Bus::batch()->name()->then()->catch()->dispatch()` constructs the import batch object step by step |
| **Factory Method** | Creational | `UserResource::toArray()` constructs API response representations from either a domain `User` entity or a `CreateUserResponse` DTO |
| **Template Method** | Behavioral | `EloquentUserRepository::findPaginated()` defines the query skeleton; `applyFilters()` delegates database-specific logic (PostgreSQL `tsquery` vs `LIKE`) |

### DDD / Clean Architecture Patterns

| Pattern | Where Used |
|---|---|
| **Value Object** | `Email` — immutable, self-validating; throws `InvalidEmailException` on bad input |
| **Entity** | `User`, `UserImport`, `Role` — identity-bearing domain objects with lifecycle |
| **Use Case (Interactor)** | One class per action (`CreateUserUseCase`, `ImportUsersUseCase`, …) each with a typed `Request` + `Response` DTO pair |
| **DTO** | `UserFilters`, `ActivityLogFilters`, `AuthUserDTO`, `ActivityLogEntry` — readonly data carriers across layer boundaries |
| **Domain Exception** | `InvalidEmailException` raised inside the domain; outer layers map it to `ApiException` subclasses |

### Laravel-Specific Patterns

| Pattern | Where Used |
|---|---|
| **Service Provider / DI Container** | `RepositoryServiceProvider`, `UseCaseServiceProvider`, `AppServiceProvider` bind interfaces to concrete implementations |
| **Form Request** | `CreateUserRequest`, `ImportUsersRequest`, `LoginRequest`, `UploadAvatarRequest` — validation + authorization colocated |
| **API Resource (Transformer)** | `UserResource` transforms domain entities to versioned JSON; decouples wire format from internal model |
| **Policy** | `UserPolicy`, `ActivityLogPolicy` — model-scoped authorization; consumed via `Gate::authorize()` |
| **Model Observer** | `UserModelObserver` registered in `AppServiceProvider::boot()` |
| **Event Broadcasting** | `ImportProgressUpdated`, `ImportCompleted` broadcast over private Reverb channels for real-time progress |
| **Notification** | `UserCreatedNotification` dispatched through `UserCreatedNotifierInterface`; delivers mail + database channels |
| **Queueable Job** | `ProcessImportChunk` implements `ShouldQueue` with `Batchable`, `Dispatchable`, `Queueable`, `SerializesModels` |
| **Soft Delete** | `SoftDeletes` trait on `UserModel`; trash / restore / force-delete lifecycle with filter support |
| **ETag / HTTP Caching** | `CacheControlMiddleware` computes ETags from Redis version tokens; short-circuits 304 responses |
| **Rate Limiter** | Three named limiters (`api` 60/min, `login` 5/min, `heavy` 10/min) defined in `AppServiceProvider` |
| **Signed URL** | Export download links are time-limited (15 min) and verified via the `signed` middleware |
| **Structured Request Logging** | `LogApiRequests` middleware records `X-Request-Id`, duration, status, user, and sanitized headers for every request |

---

## Quick Start

```bash
composer run setup     # Install deps, copy .env, generate key, migrate
composer run dev:infra # Start Docker (Redis, MinIO)
composer run dev       # Start artisan serve + Horizon + Pail + Reverb
```

---

## API Reference

All routes are prefixed `/v1` with `throttle:api` (60 req/min).

**Tenant Resolution**: Routes requiring a tenant use the `X-Tenant-ID` header (accepts tenant ID as integer or slug). Super-admin users can omit this header for cross-tenant access.

### Authentication

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/v1/register` | — | Register a new user |
| `POST` | `/v1/login` | — | Login (5 req/min) |
| `POST` | `/v1/logout` | ✓ | Revoke current token |
| `GET` | `/v1/me` | ✓ | Authenticated user details |
| `POST` | `/v1/me/avatar` | ✓ | Upload own avatar |

### Users (admin-gated)

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/v1/users` | ✓ admin | Paginated list with search + sort |
| `GET` | `/v1/users/stats` | ✓ admin | Total active / deleted / created today |
| `POST` | `/v1/users` | ✓ admin | Create user |
| `DELETE` | `/v1/users/{id}` | ✓ admin | Soft delete |
| `POST` | `/v1/users/{id}/restore` | ✓ admin | Restore soft-deleted user |
| `DELETE` | `/v1/users/{id}/force` | ✓ admin | Permanent delete |
| `POST` | `/v1/users/{user}/avatar` | ✓ | Upload user avatar |

### CSV Import / Export (10 req/min)

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/v1/users/import` | ✓ admin | Upload CSV, start async batch processing |
| `GET` | `/v1/users/import/{id}/status` | ✓ | Real-time import progress |
| `DELETE` | `/v1/users/import/{id}` | ✓ admin | Cancel in-progress import |
| `GET` | `/v1/users/export` | ✓ admin | Trigger CSV export, returns signed URL |
| `GET` | `/v1/users/export/last` | ✓ admin | Metadata for last export |
| `GET` | `/v1/users/export/download` | ✓ signed | Download export file |

### Activity Logs (admin-gated)

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/v1/activity-logs` | ✓ admin | Paginated list with filters |
| `GET` | `/v1/activity-logs/{id}` | ✓ admin | Single entry |
| `GET` | `/v1/users/{user}/activity-logs` | ✓ admin | Activity for a specific user |

### Health

| Method | Path | Description |
|---|---|---|
| `GET` | `/health` | Checks DB, Redis, MinIO connectivity |

---

## Features

### Role-Based Authorization

- **RBAC tables** — `roles`, `permissions`, `role_user`, `permission_role` replace the old `role` string column
- `admin` Gate and all policy `isAdmin()` checks resolve via `$user->hasRole('admin')` on the `UserModel`
- Policy classes: `UserPolicy`, `ActivityLogPolicy`
- Users can view/update their own profile regardless of role
- Default roles seeded: `admin` (all permissions) and `user` (self-access only)
- 12 permissions seeded across two groups: `users.*` and `activity-logs.*`

### Async CSV Import

1. Upload CSV via `POST /v1/users/import`
2. File is streamed in memory-efficient chunks (500 rows/chunk)
3. Each chunk dispatched as a `ProcessImportChunk` job (3 retries, 300s timeout)
4. Progress broadcast over WebSocket channel `imports.{importId}`
5. Final `ImportCompleted` event sent on batch success or failure

Row validation: `name`, `email`, `password` required; password min 8 chars; valid email format. Invalid rows are collected in the `errors` JSON column — valid rows are still upserted.

### ETag Caching

Redis version tokens are incremented on every entity write (via `UserModelObserver`). The `CacheControlMiddleware` uses these tokens to generate ETags and short-circuit `304 Not Modified` responses without hitting the database.

| Route | max-age | Version key |
|---|---|---|
| `GET /v1/users` | 60s | `version:users` |
| `GET /v1/users/stats` | 300s | `version:users` |
| `GET /v1/activity-logs` | 60s | `version:activity-logs` |

Falls back to MD5 body hash when Redis is unavailable.

### Activity Logging

Every user write (created, updated, deleted, restored, force-deleted) is automatically logged by `UserModelObserver`:
- Polymorphic `subject` (what changed) and `causer` (who changed it)
- Sensitive attributes (`password`, `remember_token`) are filtered
- Causer is nullable for queue/CLI operations
- Bulk import via `upsert()` does **not** emit per-row activity

### Search

- **PostgreSQL**: native full-text search (`tsquery`) with prefix matching and GIN indexes on `users(name, email)`
- **Other databases**: `LIKE` fallback

### Soft Deletes

Users support soft deletion — `DELETE /v1/users/{id}` sets `deleted_at` rather than removing the row. Soft-deleted users are excluded from normal queries but can be:

- Listed via the `trashed` filter on `GET /v1/users`
- Restored via `POST /v1/users/{id}/restore`
- Permanently removed via `DELETE /v1/users/{id}/force`

The `ListUsersUseCase` accepts a `trashed` filter (`only`, `with`, or omitted for active-only). `GetUserStatsUseCase` tracks active and deleted counts independently.

### File Storage

Configurable per operation via environment variables:

| Operation | Env var | Default |
|---|---|---|
| Avatars | `AVATARS_DISK` | `public` |
| Imports | `IMPORTS_DISK` | `local` |
| Exports | `EXPORTS_DISK` | `local` |

Set to `minio` (or any S3-compatible config) for cloud storage.

---

## Domain Model

### Entities

**`User`** — id, name, email (`Email` VO), password, avatarPath, roles (`Role[]`), tenantId, timestamps, deletedAt

**`Role`** — id, name, slug, description

**`Tenant`** — id, name, slug, settings (JSON), isActive, timestamps. Users belong to a single tenant; admins can operate cross-tenant via super_admin role.

**`UserImport`** — id, batchId, filePath, status (`pending|processing|completed|failed|cancelled`), totalRows, processedRows, createdCount, updatedCount, errors[], tenantId, timestamps

### Value Objects

**`Email`** — Immutable, validates via `filter_var()`. Throws `InvalidEmailException` on invalid input.

---

## Error Handling

All errors return a consistent JSON envelope. Never use plain `response()->json()` — throw the appropriate exception class:

| HTTP | Exception |
|---|---|
| 400 | `ValidationException` |
| 401 | `UnauthorizedException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 500 | `InternalServerException` |
| 503 | `ServiceUnavailableException` |

All extend `ApiException`. The global handler in `bootstrap/app.php` serializes them to `{ message, error_code, context }`.

---

## Testing

```bash
composer run test                                          # Full suite
php artisan test                                           # All tests
php artisan test --filter=UserImportApiTest                # Single class
php artisan test tests/Feature/UserImportApiTest.php       # Single file
```

- PHPUnit 11, Feature + Unit suites
- SQLite in-memory database
- Array cache driver, sync queue driver
- Telescope, Reverb, and broadcasting disabled in test env

---

## Maintenance Commands

All commands run inside the Docker container:

```bash
docker compose exec app php artisan <command>
```

### Operations

```bash
# Print import activity summary (last 30 days)
php artisan app:report-import-stats

# Reset recently failed imports back to pending for re-processing
php artisan app:retry-failed-imports

# Mark imports stuck in pending/processing for > 2 h as failed
php artisan app:cancel-stale-imports

# Pre-populate Redis user-stats cache after a cold deploy or cache flush
php artisan app:cache-warm
```

### Pruning

All prune commands accept `--dry-run` to preview what would be deleted.

```bash
php artisan app:prune-imports           # Completed/failed imports older than 30 days (records + CSV files)
php artisan app:prune-activity-logs     # Activity log entries older than 90 days
php artisan app:prune-telescope         # Telescope debug entries older than 48 hours
php artisan app:prune-password-resets   # Expired password reset tokens (> 24 h)
```

These are scheduled automatically via `routes/console.php` when the Laravel scheduler is running in the container.

---

## Code Style

```bash
vendor/bin/pint        # Auto-format (Laravel preset)
```

- Strict types (`declare(strict_types=1)`)
- 2-space indent for PHP/YAML (EditorConfig)
- LF line endings

---

## Database Schema

| Table | Purpose |
|---|---|
| `users` | Core user records with soft deletes |
| `roles` | Named roles (e.g. `admin`, `user`) |
| `permissions` | Granular permissions grouped by resource |
| `role_user` | Many-to-many: users ↔ roles |
| `permission_role` | Many-to-many: roles ↔ permissions |
| `personal_access_tokens` | Sanctum API tokens |
| `user_imports` | Import job state and progress tracking |
| `activity_log` | Polymorphic audit trail |
| `jobs` | Queue job storage |
| `sessions` | Session storage |
| `cache` | Cache storage |
| `notifications` | Laravel notifications |
| `telescope_entries` | Telescope debug data |

---

## Environment Variables

Key variables to configure (see `.env.example` for the full list):

```dotenv
# App
APP_ENV=local
APP_URL=http://localhost:8000
BCRYPT_ROUNDS=12

# Database
DB_CONNECTION=sqlite      # or mysql / pgsql

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=database

# Broadcasting
BROADCAST_CONNECTION=reverb

# Storage (local or minio)
AVATARS_DISK=public
FILESYSTEM_DISK=local

# MinIO (when using S3-compatible storage)
MINIO_ENDPOINT=http://localhost:9000
MINIO_ACCESS_KEY_ID=
MINIO_SECRET_ACCESS_KEY=
MINIO_BUCKET=
```

---

## License

MIT
