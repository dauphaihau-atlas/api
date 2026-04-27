# ADR-002: Use Cases as the Primary Business Logic Entry Point

## Status

Accepted

## Date

2026-04-27

## Context

Within the Clean Architecture (ADR-001), the Application layer needs a consistent pattern for encapsulating business operations. Without one, logic drifts into controllers, event listeners, or repositories — all of which are harder to test and reuse.

We need a pattern that:
- Makes each business operation independently testable
- Enforces a clear input/output contract
- Keeps controllers thin (HTTP translation only)

## Options Considered

### Option A: Fat controllers
- Pros: no extra files, fast to write
- Cons: logic is tied to HTTP; untestable without an HTTP request

### Option B: Single service classes per domain (e.g. `UserService`)
- Pros: fewer files than use cases
- Cons: services grow large; a single class accumulates unrelated operations; harder to enforce the single-responsibility principle

### Option C: Use cases with Request/Response DTOs (chosen)
- Pros: one class per operation; explicit typed input/output; no HTTP dependency; trivially unit-testable
- Cons: more files; the Request/Response pair is boilerplate for simple CRUD

## Decision

Each business operation is a dedicated use case class with an `execute(XxxRequest): XxxResponse` signature:

```
app/Core/Application/UseCases/{Domain}/{Operation}/
├── {Operation}UseCase.php    # single public method: execute()
├── {Operation}Request.php    # plain PHP object carrying validated input
└── {Operation}Response.php   # plain PHP object carrying output data
```

Example: `CreateUserUseCase::execute(CreateUserRequest): CreateUserResponse`

Controllers translate HTTP input into a `Request` DTO, call `execute()`, and translate the `Response` DTO into a JSON resource. They contain no business logic.

Use cases depend only on interfaces defined in `Application/Contracts/` — never on Eloquent models or Laravel facades directly (except `Hash` and `Storage` where necessary).

## Consequences

- Adding a feature means creating a new use case triplet rather than extending an existing class, keeping each file small and focused.
- Use cases are unit-testable by injecting mock repositories — no HTTP or database required.
- The `execute()` contract makes it straightforward to call use cases from jobs or commands, not just controllers (e.g. `ImportUsersUseCase` is called from `UserController` and its result drives `ProcessImportChunk` job dispatch).
- Simple read operations (e.g. `GetUserStatsUseCase`) still require the triplet even when there is minimal logic — accepted as consistent overhead.
