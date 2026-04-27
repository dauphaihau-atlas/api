# ADR-012: Event + Listener Pattern for Side Effects

## Status

Accepted

## Date

2026-04-27

## Context

As the application grew, use cases and jobs accumulated direct dependencies on unrelated concerns. Three problem areas emerged:

1. **`CreateUserUseCase`** injected `UserCreatedNotifierInterface` — a notification concern embedded in a creation concern. Adding a second post-creation side effect (audit, webhook) would require modifying the use case and its DI bindings.

2. **`UserModelObserver`** mixed cache invalidation and activity logging in every method. Changing the cache strategy meant touching the same file as the logging logic, violating the Single Responsibility Principle.

3. **`ImportUsersUseCase`, `CancelImportUseCase`, and `ProcessImportChunk`** directly dispatched Reverb broadcasting events (`ImportCompleted`, `ImportProgressUpdated`). Application-layer code (use cases) and a job were coupled to the WebSocket infrastructure layer.

## Options Considered

### Option A: Keep direct dependencies (status quo)
- Pros: no new files, straightforward call stack
- Cons: each side effect adds a constructor parameter or inline call to the triggering class; adding or removing effects requires modifying business logic code; cross-cutting concerns accumulate in use cases

### Option B: Extract helper methods, no structural change
- Pros: slightly more readable
- Cons: same coupling — the use case still knows about and depends on every side effect; still not independently extensible

### Option C: Event + Listener pattern (chosen)
- Pros: triggering code dispatches one event and has no knowledge of what happens next; each listener has a single responsibility; new side effects are registered in `EventServiceProvider` without touching the triggering class
- Cons: indirect flow is harder to trace; event/listener files multiply

## Decision

We introduce application-level events in `app/Events/` and listeners in `app/Listeners/`, registered in a new `App\Providers\EventServiceProvider`.

**`CreateUserUseCase`** drops `UserCreatedNotifierInterface` injection and dispatches `App\Events\UserCreated` carrying the domain `User` entity. `SendUserCreatedNotification` listens and queues `UserCreatedNotification`. The notifier interface and its implementation are deleted.

**`UserModelObserver`** is deleted and replaced by two single-responsibility observers registered on the same model:
- `UserCacheObserver` — handles `Cache::tags` flush and `version:users` increment
- `UserActivityLogObserver` — handles `ActivityLogModel` writes and `version:activity-logs` increment

Eloquent model events already implement the event + listener contract at the framework level; a custom event layer between them and the observers would add indirection without benefit. Two focused observers achieve the same separation.

**Import lifecycle** — `ImportUsersUseCase` and `CancelImportUseCase` dispatch `App\Events\ImportCompleted`; `ProcessImportChunk` dispatches `App\Events\ImportChunkProcessed`. Both events carry the `UserImport` domain entity. Two listeners translate to the existing Reverb broadcasting events:
- `BroadcastImportCompleted` → `Infrastructure\Broadcasting\Events\ImportCompleted`
- `BroadcastImportProgress` → `Infrastructure\Broadcasting\Events\ImportProgressUpdated` (also computes `progressPercentage`)

The broadcasting infrastructure classes are now implementation details of the listener layer — nothing outside `app/Listeners/` imports them for dispatch.

## Consequences

- Adding a new post-creation side effect (e.g. webhook, audit log) requires a new listener registered in `EventServiceProvider` — the use case is untouched.
- The observer split means cache and activity-log concerns can evolve, be disabled, or be replaced independently.
- Use cases and jobs no longer depend on WebSocket infrastructure; switching from Reverb to another transport means updating one listener class.
- The event dispatch call stack is one level deeper — debugging requires checking `EventServiceProvider` to discover which listeners fire for a given event.
- `ProcessImportChunk` retains its try/catch around the event dispatch to ensure a failed broadcast cannot abort an in-flight import job (see ADR-006).
