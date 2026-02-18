# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12 API-only admin dashboard following **Clean Architecture** with Sanctum token auth, Horizon job queues, Reverb WebSockets, and Redis caching.

## Common Commands

```bash
composer run setup     # Full setup: install, .env, key:generate, migrate
composer run dev       # Start dev servers (artisan serve, horizon, pail, reverb)
composer run test      # Clear config cache + run PHPUnit

php artisan test                                    # Run all tests
php artisan test --filter=UserImportApiTest          # Run single test class
php artisan test tests/Feature/UserImportApiTest.php # Run single test file

vendor/bin/pint        # Code formatting (Laravel preset)
```

## Architecture (Clean Architecture)

Four layers with strict dependency rules (inner layers never depend on outer):

1. **Domain** (`app/Core/Domain/`) — Entities (`User`, `UserImport`), Value Objects (`Email`), domain exceptions
2. **Application** (`app/Core/Application/`) — Use cases, contracts (interfaces), DTOs. Use cases are the entry point for all business logic (e.g., `CreateUserUseCase`, `ImportUsersUseCase`)
3. **Infrastructure** (`app/Infrastructure/`) — Eloquent models/repositories, auth (Sanctum), broadcasting, notifications, external services
4. **Presentation** (`app/Presentation/Http/`) — Controllers, form requests, API resources, middleware

### Dependency Injection

Bindings are split across dedicated providers in `app/Providers/`:
- **RepositoryServiceProvider** — Repository interfaces → Eloquent implementations
- **UseCaseServiceProvider** — Service interfaces (`EmailServiceInterface`, `AuthServiceInterface`, `UserCreatedNotifierInterface`) → concrete implementations
- **AppServiceProvider** — Gates (`admin` role check), rate limiter config

### API Routes

All API routes in `routes/api.php` under prefix `v1` with `throttle:api` middleware (60 req/min).

Key endpoints: auth (login/register/logout), user CRUD (admin-gated), avatar upload, CSV import/export with async processing, health check.

### Error Handling

**Always use exception classes from `app/Exceptions/`** instead of plain JSON responses. The global exception handler in `bootstrap/app.php` renders consistent API payloads.

| HTTP Status | Exception Class |
|---|---|
| 400 | `ValidationException` |
| 401 | `UnauthorizedException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 500 | `InternalServerException` |
| 503 | `ServiceUnavailableException` |

All extend `ApiException` base class.

### Background Jobs

`ProcessImportChunk` handles async CSV import with batch processing. Progress is broadcast via Reverb on channel `imports.{importId}` using `ImportProgressUpdated` event.

## Testing

- PHPUnit 11 with Feature and Unit suites
- Tests use SQLite in-memory DB, array cache, sync queue
- Telescope, Reverb, and broadcasting are disabled in test environment (see `phpunit.xml`)
- Base test class: `Tests\TestCase`

## Code Style

- **Laravel Pint** with `laravel` preset (see `pint.json`)
- Fully qualified strict types enabled
- EditorConfig: 2-space indent for PHP/YAML, LF line endings

## Key Infrastructure

- **Auth**: Sanctum tokens, role-based gates (`admin`)
- **Queue**: Database driver, managed by Horizon
- **Cache**: Redis
- **Storage**: Local or MinIO (S3-compatible) for avatars and imports
- **Broadcasting**: Reverb WebSocket server
- **Activity log**: Table `activity_log`; observers for `UserModel` record who changed what and when. Causer comes from `auth()` (nullable in queue/CLI). Bulk user import uses `DB::table()->upsert()` and does not emit per-row activity.