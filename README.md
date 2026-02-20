# Admin Dashboard API

A production-ready **API-only admin dashboard** built with Laravel 12, following Clean Architecture principles. Features role-based access, async CSV import/export, real-time WebSocket broadcasting, Redis caching with ETag support, and comprehensive activity logging.

---

## Applied Techniques

- **Clean Architecture** — Domain, Application, Infrastructure, and Presentation layers with strict inward-only dependencies
- **Role-Based Authorization** — Policy classes + admin Gate guard all privileged endpoints
- **Queue, Jobs & Workers** — Async CSV import dispatched as batched jobs via Horizon, with retry and timeout handling
- **Soft Deletes** — Trash / restore / force-delete lifecycle on users with filter support
- **Response Caching with ETag** — Redis version tokens drive `304 Not Modified` short-circuits; falls back to MD5 body hash
- **File Storage** — Avatar upload, CSV import ingestion, and export generation; configurable between local disk and MinIO (S3-compatible)
- **Full-Text Search** — PostgreSQL `tsquery` with GIN indexes on users; `LIKE` fallback for other databases
- **Activity Log** — Model observers capture every user write (created, updated, deleted, restored, force-deleted), recording subject, causer, and changed attributes
- **Real-Time Broadcasting** — Import progress streamed over private WebSocket channels via Laravel Reverb
- **Rate Limiting** — Three tiers: standard API (60/min), login (5/min), heavy operations like import/export (10/min)
- **API Token Authentication** — Sanctum tokens with per-request revocation on logout
- **Signed URLs** — Export download links are time-limited (15-min expiry) and verified via Laravel's `signed` middleware
- **Structured Error Handling** — Custom `ApiException` hierarchy maps every failure to a consistent `{ message, error_code, context }` JSON envelope
- **Notifications** — `UserCreatedNotification` dispatched on user creation via a `UserCreatedNotifierInterface` contract
- **Structured Request Logging** — Every API request is logged with `X-Request-Id` tracing, route name, duration (ms), user ID, and IP; log level scales with status code (info / warning / error); sensitive headers stripped before logging
- **Built-in Web Dashboards** — Horizon for queue monitoring, Telescope for request/query/exception inspection, Scribe for auto-generated API documentation

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
| `AppServiceProvider` | Gates (admin role), rate limiter config |

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

- `admin` role gate controls all privileged endpoints
- Policy classes: `UserPolicy`, `ActivityLogPolicy`
- Users can view/update their own profile regardless of role

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

**`User`** — id, name, email (`Email` VO), password, avatarPath, role, timestamps, deletedAt

**`UserImport`** — id, batchId, filePath, status (`pending|processing|completed|failed|cancelled`), totalRows, processedRows, createdCount, updatedCount, errors[], timestamps

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
