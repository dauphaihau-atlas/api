# Bootstrap Providers

This document explains how Laravel loads service providers and how `bootstrap/providers.php` is used in this project.

## Overview

`bootstrap/providers.php` returns an array of service provider class names. Laravel **automatically** loads this file during application bootstrap—you do not need to import or reference it anywhere.

## How Laravel Loads It

1. When the app boots via `bootstrap/app.php`, Laravel calls `Application::configure()`.
2. The configuration chain includes `->withProviders()`, which registers the path to `bootstrap/providers.php`.
3. During bootstrap, the `RegisterProviders` bootstrapper:
   - Requires `bootstrap/providers.php` to get the providers array
   - Merges those providers into `config('app.providers')`
   - Registers them via `$app->registerConfiguredProviders()`

No manual require or import is needed.

## Providers in This Project

| Provider | Purpose |
|----------|---------|
| `AppServiceProvider` | Default Laravel provider for app-wide services. Typically empty. |
| `RepositoryServiceProvider` | Binds repository interfaces to Eloquent implementations (e.g. `UserRepositoryInterface` → `EloquentUserRepository`). |
| `UseCaseServiceProvider` | Binds service interfaces to implementations (e.g. `AuthServiceInterface` → `SanctumAuthService`, `EmailServiceInterface` → `LogEmailService`). |

## Adding a New Provider

1. Create the provider class in `app/Providers/`.
2. Add it to the array in `bootstrap/providers.php`.

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
    App\Providers\UseCaseServiceProvider::class,
    App\Providers\YourNewProvider::class,  // add here
];
```

Laravel will load and register it automatically on the next request.

## Relation to Architecture

The providers wire up **dependency injection** for the Clean Architecture layers:

- **Repository layer**: `RepositoryServiceProvider` maps repository interfaces to concrete Eloquent implementations.
- **Application layer**: `UseCaseServiceProvider` maps service interfaces (`AuthServiceInterface`, `EmailServiceInterface`, etc.) to their implementations.

See [ARCHITECTURE.md](ARCHITECTURE.md) and [DEPENDENCY-INJECTION.md](DEPENDENCY-INJECTION.md) for more context.
