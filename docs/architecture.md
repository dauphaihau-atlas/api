# Clean Architecture

This Laravel app is organized using **Clean Architecture** so that business logic stays independent of the framework and infrastructure.

## Layers

### 1. Domain (`app/Core/Domain`)

- **Entities**: Plain PHP domain objects (e.g. `User`). No framework dependencies.
- **ValueObjects**: Immutable values with validation (e.g. `Email`).
- **Exceptions**: Domain-specific exceptions (e.g. `InvalidEmailException`).
- **Events**: (Optional) Domain events.

Dependencies point **inward**: Domain does not depend on anything else.

### 2. Application (`app/Core/Application`)

- **Contracts**: Interfaces for repositories and external services (e.g. `UserRepositoryInterface`, `EmailServiceInterface`).
- **UseCases**: Application use cases. Each use case has a Request, Response, and Execute method. They depend only on Domain and Contracts.
- **DTOs**: (Optional) Data transfer objects.

Application depends only on **Domain** and defines **Contracts** implemented by Infrastructure.

### 3. Infrastructure (`app/Infrastructure`)

- **Persistence/Eloquent**: Eloquent models (`UserModel`) and repository implementations (e.g. `EloquentUserRepository`).
- **External**: Implementations of external services (e.g. `LogEmailService` for email).

Infrastructure implements **Application contracts** and uses **Domain** entities. It is the only layer that knows about Laravel (Eloquent, HTTP, etc.).

### 4. Presentation (`app/Presentation`)

- **Http/Controllers**: Thin controllers that validate input, call use cases, and return HTTP responses.
- **Http/Requests**: Form request validation.
- **Http/Resources**: API resources for serialization.

Presentation depends on **Application** (use cases) and **Domain** (for resources). It does not depend on Infrastructure directly; dependency injection resolves interfaces to implementations.

## Dependency rule

- **Domain** ← Application ← Infrastructure
- **Domain** ← Application ← Presentation

Infrastructure and Presentation never depend on each other; both depend on Application (interfaces) and Domain.

## Adding a new feature

1. **Domain**: Add or reuse entities, value objects, and exceptions.
2. **Application**: Define repository/service interfaces if needed, then add a use case (Request, Response, UseCase class).
3. **Infrastructure**: Implement new interfaces (repositories, external services) and register them in `RepositoryServiceProvider` or `UseCaseServiceProvider`.
4. **Presentation**: Add controller actions, form requests, and resources that call the use case.

## API routes

- API routes live in `routes/api.php` and use the `Presentation\Http\Controllers\Api\V1` namespace.
- Example: `GET /api/v1/users`, `POST /api/v1/users` (see `UserController`).

## Service providers

- `RepositoryServiceProvider`: Binds repository interfaces to Eloquent implementations.
- `UseCaseServiceProvider`: Binds external service interfaces (e.g. email) to implementations.

Both are registered in `bootstrap/providers.php`.
