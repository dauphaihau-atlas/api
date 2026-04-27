# ADR-011: Value Objects for Domain Constraints

## Status

Accepted

## Date

2026-04-27

## Context

Primitive types like `string` carry no semantic guarantees — a `string $email` parameter could be empty, malformed, or never validated. Validation of these constraints typically ends up in Laravel `FormRequest` classes, which means the check only runs at the HTTP boundary and is silently absent when code is called from jobs, CLI commands, or tests.

We need a way to guarantee that certain domain invariants hold everywhere a concept is used, not just at the HTTP layer.

## Options Considered

### Option A: Validate only in FormRequest
- Pros: zero extra classes; validation rules in one place
- Cons: the invariant is not enforced when the value is constructed outside an HTTP request (e.g. in a job, seeder, or direct use case call); a `string` typed parameter provides no self-documentation of its constraints

### Option B: Setter validation (validate-on-assignment)
- Pros: catches bad values at the point of assignment
- Cons: mutable state; multiple assignment paths to guard; no identity equality semantics

### Option C: Immutable Value Object that validates in the constructor (chosen)
- Pros: impossible to construct an invalid instance anywhere in the codebase; value carries its own equality semantics (`equals()`); self-documenting in method signatures
- Cons: requires wrapping/unwrapping at the persistence boundary; the string value must be extracted via `getValue()` before passing to Eloquent

## Decision

Value Objects are pure PHP classes in `app/Core/Domain/ValueObjects/`. They are immutable (all properties `readonly`), validate in the constructor, and throw a domain exception on failure:

```php
// app/Core/Domain/ValueObjects/Email.php
class Email
{
    public function __construct(private readonly string $value)
    {
        if ($value === '' || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email address: {$value}");
        }
    }

    public function getValue(): string { return $this->value; }
    public function equals(Email $other): bool { return $this->value === $other->value; }
    public function __toString(): string { return $this->value; }
}
```

`InvalidEmailException` extends `DomainException` — a plain PHP exception with no Laravel dependency. The global exception renderer in `bootstrap/app.php` catches it and re-throws it as `ValidationException` (HTTP 422) so the client receives a consistent error shape (see ADR-004).

**Where Value Objects are constructed:** use cases construct them from validated input (`new Email($request->email)` in `CreateUserUseCase`). Repositories receive and return plain `string` because Eloquent cannot store a Value Object directly; `toEntity()` passes the raw string and the entity constructor accepts `string|Email` as appropriate.

**Persistence boundary:** `Infrastructure/Persistence/Eloquent/Casts/EmailCast.php` handles casting the `email` column on `UserModel` to/from a string — the Eloquent model never holds an `Email` VO.

## Consequences

- Any code path that constructs `new Email($value)` is guaranteed to have a valid email address from that point forward — no downstream null/format checks needed.
- The `filter_var(FILTER_VALIDATE_EMAIL)` check is the single source of truth for what constitutes a valid email; changing the rule means changing one constructor.
- The `InvalidEmailException` → `ValidationException` mapping in the global renderer must exist and be kept in sync. If the mapping is removed, an invalid email in a non-HTTP context would produce a 500 instead of a 422.
- New domain constraints (e.g. `PhoneNumber`, `Slug`) should follow the same pattern: Value Object in `Domain/ValueObjects/`, domain exception in `Domain/Exceptions/`, renderer mapping in `bootstrap/app.php`.
