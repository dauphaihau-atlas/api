# Authorization

This document describes how authorization is implemented in the admin dashboard API.

## Overview

- **Authentication**: Laravel Sanctum (token-based).
- **Authorization**: Role-based access control (RBAC) via Laravel Gates.
- **Roles**: `admin`, `user` (default for new registrations).

## Roles

| Role  | Description                                                |
|-------|------------------------------------------------------------|
| `user` | Default for new registrations. Can access `/me`, `/logout`. |
| `admin` | Admin only. Can access `/users` (index, store) in addition to user routes. |

## Protected Routes

| Route | Method | Auth | Role  |
|-------|--------|------|-------|
| `/api/v1/login` | POST | No | - |
| `/api/v1/register` | POST | No | - |
| `/api/v1/logout` | POST | Yes | `user` or `admin` |
| `/api/v1/me` | GET | Yes | `user` or `admin` |
| `/api/v1/users` | GET | Yes | `admin` only |
| `/api/v1/users` | POST | Yes | `admin` only |

## HTTP Status Codes

- **401 Unauthorized**: Not authenticated (missing or invalid token). Uses `UnauthorizedException`.
- **403 Forbidden**: Authenticated but not allowed (e.g. role insufficient). Uses `AuthorizationException` (handled in JSON format).

## Creating an Admin User

New users created via `POST /api/v1/register` receive `role=user` by default. To promote a user to admin:

```sql
UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
```

Or via tinker:

```bash
php artisan tinker
>>> $u = App\Infrastructure\Persistence\Eloquent\Models\UserModel::where('email', 'admin@example.com')->first();
>>> $u->role = 'admin';
>>> $u->save();
```

## Adding New Gates

Define gates in `AppServiceProvider::boot()`:

```php
Gate::define('admin', fn ($user) => $user !== null && $user->role === 'admin');
```

Protect routes in `routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
    // admin-only routes
});
```

## Testing

- Use `UserModel::factory()->admin()->create()` to create an admin user in tests.
