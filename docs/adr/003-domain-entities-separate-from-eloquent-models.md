# ADR-003: Domain Entities Separate from Eloquent Models

## Status

Accepted

## Date

2026-04-27

## Context

Eloquent models are framework-coupled objects: they carry HTTP casting, query scopes, relationships, observers, and serialization logic. Using them directly as domain entities would make the Domain layer depend on Laravel, violating ADR-001's inward dependency rule.

We need a way to represent domain concepts (`User`, `Tenant`, `UserImport`) without any ORM dependency while still using Eloquent for persistence.

## Options Considered

### Option A: Use Eloquent models directly as entities
- Pros: no mapping overhead; idiomatic Laravel
- Cons: Domain layer depends on Eloquent; models become god objects mixing persistence and business concerns; unit tests require the ORM stack

### Option B: Hybrid — extend Eloquent but add domain methods
- Pros: less boilerplate than full separation
- Cons: still couples domain logic to Eloquent's lifecycle; casting, `$fillable`, and relationships pollute the entity interface

### Option C: Separate pure PHP entities + Eloquent models + repository mapping (chosen)
- Pros: Domain entities are plain PHP — testable with `new User(...)` and no database; Eloquent models handle all ORM concerns independently
- Cons: `toEntity()` mapping in each repository adds boilerplate; two representations of each aggregate must stay in sync when columns change

## Decision

Each aggregate has two representations:

| Layer | Class | Purpose |
|---|---|---|
| Domain | `App\Core\Domain\Entities\User` | Pure PHP, holds business invariants |
| Infrastructure | `App\Infrastructure\Persistence\Eloquent\Models\UserModel` | Eloquent ORM, handles persistence |

Repositories (`EloquentUserRepository`) own the mapping. The `toEntity(UserModel): User` private method converts from ORM to domain. The `save(User): User` method converts the other way before persisting.

```php
// EloquentUserRepository::toEntity()
return new User(
    id: $model->id,
    name: $model->name,
    email: $model->email,   // string; Email VO constructed in use cases
    ...
);
```

Domain entities are immutable-by-convention: all properties are set via constructor; mutation returns a new instance or goes through a repository `save()`.

## Consequences

- Adding or renaming a database column requires updating both the migration, `UserModel`, and the `toEntity()` mapping — three touch points instead of one.
- Domain entities are fully testable with `new User(...)` — no database, no Eloquent boot.
- Eloquent-specific features (soft deletes, observers, casts) live exclusively in `UserModel` and never leak into business logic.
- The `upsertBatch` path in `EloquentUserRepository` intentionally uses `DB::table()` instead of the entity pattern to bypass the password cast for bulk import — this exception is documented in code.
