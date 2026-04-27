# ADR-001: Clean Architecture Four-Layer Structure

## Status

Accepted

## Date

2026-04-27

## Context

Laravel encourages placing business logic in controllers, models, or service classes without prescribing a boundary between framework concerns and application logic. As the codebase grows with multiple domains (users, tenants, imports, activity logs), tight coupling between HTTP handling, persistence, and business rules makes individual layers difficult to test and replace.

The project requires:
- Business logic testable without booting the HTTP layer or hitting a real database
- The ability to swap Eloquent for another persistence mechanism without touching business rules
- A clear answer to "where does this code go?" for every new feature

## Options Considered

### Option A: Standard Laravel MVC
- Pros: familiar to all Laravel developers, minimal boilerplate
- Cons: business logic ends up in fat controllers or models; no enforced boundary

### Option B: Service Layer (Controllers → Services → Models)
- Pros: removes logic from controllers, simple to understand
- Cons: services still depend on Eloquent directly; swapping persistence still requires touching business code

### Option C: Clean Architecture (chosen)
- Pros: strict dependency inversion; inner layers are framework-agnostic; each layer has a single responsibility
- Cons: more files per feature; requires discipline to maintain dependency direction

## Decision

We use a four-layer Clean Architecture with a strict inward dependency rule — outer layers depend on inner, never the reverse:

```
app/
├── Core/
│   ├── Domain/          # Layer 1 — pure PHP: Entities, Value Objects, domain exceptions
│   └── Application/     # Layer 2 — use cases, repository/service interfaces, DTOs
├── Infrastructure/      # Layer 3 — Eloquent models, repositories, auth, broadcasting
└── Presentation/Http/   # Layer 4 — controllers, middleware, form requests, resources
```

**Dependency direction:** `Domain ← Application ← Infrastructure`, `Domain ← Application ← Presentation`.  
Infrastructure and Presentation both depend on Application interfaces; they never depend on each other.

## Consequences

- Every new feature requires creating files at multiple layers (use case, interface, repository, controller), which is more upfront work than plain MVC.
- Domain and Application layers have zero Laravel dependencies, making them fast to unit-test in isolation.
- Swapping Eloquent for a different ORM only requires changing `Infrastructure/` — use cases are unaffected.
- New contributors must learn the layer model before they can place code correctly. See `docs/architecture.md` for the overview.
