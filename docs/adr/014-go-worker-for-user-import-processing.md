# ADR-014: Go Worker for User Import Processing

## Status

Accepted

## Date

2026-04-28

## Context

ADR-006 established the original Laravel-only CSV import flow: stream the file, split it into chunks, dispatch Laravel queue jobs, and broadcast progress through Reverb.

We now want to learn and compare how an external Go module/server can handle heavy or high-performance backend tasks while keeping Laravel as the main API backend. User import is a good candidate because it already has measurable heavy work:

- CSV parsing and row counting
- Chunking large files
- Per-row validation
- Batched user writes
- Progress updates over the existing import status and Reverb channels

The new design must:

- Keep the public API stable
- Preserve the old Laravel queue implementation for performance comparison
- Avoid duplicating user write rules in Go too early
- Work with both local and MinIO-backed import storage
- Keep progress/status updates visible through the existing Laravel import model

## Options Considered

### Option A: Keep all import work in Laravel

- Pros: simplest runtime topology; already implemented; no network callback failure mode
- Cons: does not teach external high-performance service boundaries; PHP workers still own parsing/chunking work

### Option B: Move the entire import to Go, including validation and database writes

- Pros: maximum Go ownership; fewer callbacks to Laravel
- Cons: duplicates domain validation and persistence rules; Go must understand tenancy, hashing, upsert behavior, and future user invariants; harder to keep Laravel as the source of truth

### Option C: Go parses/chunks, Laravel validates/writes chunks (chosen)

- Pros: introduces a real external processing service while keeping Laravel as the authority for user rules, database writes, progress tracking, and broadcasting; allows direct comparison with the old Laravel processor
- Cons: callback traffic from Go to Laravel; more moving parts; failures must be reflected back into `user_imports`

## Decision

Create a new Go service at:

```text
apps/worker-go
```

The default import processor is now Go:

```http
POST /api/v1/users/import
POST /api/v1/users/import?processor=go
```

The old Laravel queue path remains available for comparison:

```http
POST /api/v1/users/import?processor=laravel
```

Laravel owns:

- Public API authentication, authorization, idempotency, and upload handling
- `user_imports` tracking records
- User validation, password hashing, and upsert behavior
- Import status API
- Reverb progress/completion broadcasting

Go owns:

- Reading the uploaded CSV through Laravel's internal file endpoint
- CSV header validation and row counting
- Streaming rows with Go's `encoding/csv`
- Sending chunks back to Laravel's internal chunk endpoint

The service boundary is represented in Laravel by `UserImportProcessorInterface` and `UserImportProcessorResolver`. Implementations are:

- `LaravelQueueUserImportProcessor`
- `GoWorkerUserImportProcessor`

The selected processor is stored on `user_imports.processor` so performance and behavior can be compared later.

### Internal Callback API

The Go worker communicates with Laravel through token-protected internal routes:

```http
GET  /api/internal/user-import-files?path=...
POST /api/internal/user-imports/{id}/started
POST /api/internal/user-imports/{id}/chunks
POST /api/internal/user-imports/{id}/complete
POST /api/internal/user-imports/{id}/fail
```

These routes are protected by `X-Internal-Token`.

### Internal Nginx Listener

The Go worker must not call the public HTTPS virtual host through Docker DNS. Calling `http://nginx` was redirected to `https://nginx`, which failed TLS verification because the certificate is issued for `api.atlas.local`, not `nginx`.

To avoid public TLS and host-name coupling for container-to-container calls, nginx exposes an internal HTTP-only listener:

```text
http://nginx:8080
```

`worker-go` uses:

```text
LARAVEL_INTERNAL_URL=http://nginx:8080
```

## Consequences

- `POST /api/v1/users/import` returns quickly after creating the import record and starting the Go worker.
- Progress and completion still flow through the existing `user_imports` table and `imports.{id}` private Reverb channel.
- The Laravel processor remains useful for baseline performance comparisons and fallback testing.
- Go does not yet write directly to Postgres. This avoids duplicating Laravel's user validation, password hashing, tenant scoping, and upsert behavior.
- Internal callback availability is now part of the import reliability path. If `worker-go` cannot reach `http://nginx:8080`, imports can remain stuck in `processing`.
- Imports require a resolved tenant to appear in tenant-scoped user lists. Super-admin requests without `X-Tenant-ID` can create imports/users with `tenant_id = null`; the UI should send `X-Tenant-ID` or the API should reject tenantless imports in a follow-up change.
