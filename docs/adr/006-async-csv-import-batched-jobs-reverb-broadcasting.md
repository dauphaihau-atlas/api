# ADR-006: Async CSV Import with Batched Jobs and Reverb WebSocket Broadcasting

## Status

Accepted

## Date

2026-04-27

## Context

Importing large CSV files (potentially thousands of rows) synchronously in an HTTP request would hit PHP execution time limits and leave the client waiting with no feedback. We need a solution that:
- Handles arbitrarily large files without exhausting PHP memory
- Gives the user real-time progress during processing
- Is resilient to partial failures (one bad chunk should not abort the whole import)
- Tracks per-row errors so users know exactly which rows failed

## Options Considered

### Option A: Synchronous import in controller
- Pros: simple
- Cons: timeout on large files; no progress feedback; memory exhaustion for large CSVs

### Option B: Single background job, polling endpoint for status
- Pros: simple async approach
- Cons: no real-time feedback; polling adds unnecessary HTTP traffic; entire import fails if the job fails

### Option C: Job batches + WebSocket broadcasting (chosen)
- Pros: chunked processing limits memory usage per job; batching provides completion/failure callbacks; `ShouldBroadcastNow` pushes progress in real time without polling; individual chunk failures don't cancel the whole batch
- Cons: more complex flow; requires Reverb WebSocket server; broadcasting failures must be swallowed to avoid crashing the job

## Decision

`ImportUsersUseCase` runs a **two-pass stream** over the CSV file:

- **Pass 1** (header validation + row count): streams the file once with `fgetcsv()` to validate headers and count non-empty rows. O(1) memory.
- **Pass 2** (job dispatch): streams the file again, builds chunks of 500 rows, and adds a `ProcessImportChunk` job per chunk to a `Bus::batch()`.

The batch is created empty first (with `then`/`catch` callbacks attached), then jobs are added via `$batch->add()` in Pass 2. This ensures callbacks are registered before any job can complete.

Each `ProcessImportChunk` job:
1. Validates rows individually (per-row errors collected, not thrown)
2. Upserts valid rows in a transaction via `UserRepositoryInterface::upsertBatch()`
3. Calls `ImportProgressUpdated::dispatch()` — a `ShouldBroadcastNow` event that pushes to the private `imports.{importId}` Reverb channel
4. Wraps the broadcast in try/catch — a broadcast failure must not fail the import job

The batch `then` callback dispatches `ImportCompleted` and marks status `Completed`. The `catch` callback marks status `Failed`.

Broadcasting auth for the `imports.*` private channel is guarded by `auth:sanctum` middleware and validated in `routes/channels.php`.

## Consequences

- Memory per job is bounded to `CHUNK_SIZE` (500) rows regardless of total file size.
- A chunk job retries up to 3 times (`$tries = 3`) before counting as failed; the batch continues processing other chunks.
- Progress events arrive via WebSocket — clients that cannot maintain a WebSocket connection must fall back to polling `GET /imports/{id}/status`.
- The CSRF token is excluded for the broadcasting auth route (`api/v1/broadcasting/auth`) because the XSRF-TOKEN cookie set on `api.*` is not readable by JS served from a parent domain. See comment in `bootstrap/app.php`.
- Broadcast failures are logged as warnings (`Log::warning`) and silently swallowed to prevent a broken Reverb connection from halting the import.
