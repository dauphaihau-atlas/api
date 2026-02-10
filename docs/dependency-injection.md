# Dependency Injection & Service Container

This document describes how dependencies are injected in the application and where interface–implementation bindings are registered.

## How injection works

Laravel’s **service container** resolves dependencies when it instantiates classes. Controllers (and other classes) declare dependencies in their constructor; the container provides the correct implementations.

No manual `new` is needed: the framework resolves types and injects them when handling a request.

## Where dependencies are bound

### RepositoryServiceProvider (`app/Providers/RepositoryServiceProvider.php`)

Binds **repository interfaces** to Eloquent implementations:

| Interface                     | Implementation              |
|------------------------------|-----------------------------|
| `UserRepositoryInterface`    | `EloquentUserRepository`    |

When any class (controller, use case, etc.) asks for `UserRepositoryInterface`, the container injects `EloquentUserRepository`.

### UseCaseServiceProvider (`app/Providers/UseCaseServiceProvider.php`)

Binds **external service interfaces** to implementations:

| Interface                | Implementation   |
|--------------------------|------------------|
| `EmailServiceInterface`  | `LogEmailService`|

Use cases that depend on email (or other external services) receive these implementations via the container.

## How the controller gets its dependencies

Example: `UserController` constructor:

```php
public function __construct(
    private readonly CreateUserUseCase $createUserUseCase,
    private readonly UserRepositoryInterface $userRepository
) {}
```

- **`UserRepositoryInterface`**: Resolved using the binding in `RepositoryServiceProvider` → `EloquentUserRepository` is injected.
- **`CreateUserUseCase`**: Concrete class with no explicit binding. The container instantiates it and resolves its constructor:
  - `UserRepositoryInterface` → from `RepositoryServiceProvider`
  - `EmailServiceInterface` → from `UseCaseServiceProvider`

So the controller receives both dependencies automatically when Laravel builds it.

## Conventions

- **Interfaces** are bound in a service provider (`RepositoryServiceProvider` for repositories, `UseCaseServiceProvider` for external services).
- **Concrete use case classes** do not need a binding; the container resolves them and their constructor dependencies.
- Both providers are registered in `bootstrap/providers.php`.

## Adding a new binding

1. **New repository**: Implement the interface in `app/Infrastructure/Persistence/Eloquent/Repositories/`, then add a `bind(Interface::class, Implementation::class)` in `RepositoryServiceProvider`.
2. **New external service**: Implement the interface in `app/Infrastructure/External/`, then add the binding in `UseCaseServiceProvider`.

See [ARCHITECTURE.md](ARCHITECTURE.md) for the overall layering and dependency rule.
