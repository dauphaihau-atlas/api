# Queue, Jobs & Workers

Async CSV import is handled by **batched jobs** processed by **Laravel Horizon**. Jobs use retry and timeout handling so large imports complete reliably without blocking the API.

---

## Overview

| Concern | Implementation |
|--------|-----------------|
| Queue driver | `config/queue.php`: default `QUEUE_CONNECTION` (e.g. `database` or `redis`) |
| Job batching | Laravel `Bus::batch()` with `job_batches` table |
| Worker process | Laravel Horizon (`config/horizon.php`) |
| Import job | `App\Jobs\ProcessImportChunk` — chunked CSV rows, 500 rows per job |
| Retry / timeout | `$tries = 3`, `$timeout = 300` (seconds) per job |

Other queued work (e.g. `UserCreatedNotification`) uses the same queue and Horizon workers.

---

## Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Client: POST /v1/users/import (CSV file)                                   │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Controller → ImportUsersUseCase::execute(ImportUsersDTO)                    │
│    ├─ Create import record in DB (status: pending)                          │
│    └─ Create empty Bus::batch() with then() / catch() callbacks             │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Stream CSV — every 500 rows                                                │
│    └─ $batch->add(new ProcessImportChunk(...))                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Horizon Worker picks up each ProcessImportChunk                            │
│    ├─ Validate rows (name / email / password)                               │
│    ├─ Upsert valid users in single DB transaction                           │
│    ├─ Increment import stats (processed / created / updated / errors)       │
│    ├─ Flush users cache, bump version:users                                 │
│    └─ Dispatch ImportProgressUpdated → Reverb WebSocket                     │
│         └─ Client receives progress on channel imports.{importId}           │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Batch completes                                                            │
│    ├─ All jobs succeed → then()  → status = completed                       │
│    │    └─ Dispatch ImportCompleted                                          │
│    └─ Any job exhausts retries → catch() → status = failed                  │
│         └─ Dispatch ImportCompleted (failed)                                │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Job: ProcessImportChunk

- **Location:** `app/Jobs/ProcessImportChunk.php`
- **Contract:** `ShouldQueue`, uses `Batchable` so the batch can be cancelled.
- **Payload:** `importId`, `rows` (up to 500 rows with `name`, `email`, `password`), `startRowIndex` (for error reporting).

**Retry and timeout**

- `$tries = 3` — job is retried up to 3 times on failure.
- `$timeout = 300` — job is killed after 300 seconds (5 minutes).

**Behaviour**

- If `$this->batch()?->cancelled()`, the job exits without processing.
- Validates each row (required name/email/password, email format, min password length); invalid rows are recorded as errors, not inserted.
- Valid rows are upserted in a single DB transaction via `UserRepositoryInterface::upsertBatch()`.
- After writing, it flushes `users` cache tag and increments `version:users`.
- Calls `UserImportRepositoryInterface::addChunkResult()` to aggregate processed/created/updated/errors.
- Dispatches `ImportProgressUpdated` so the frontend can show progress over WebSockets.

---

## Batching (Bus::batch)

- **Use case:** `ImportUsersUseCase` creates one batch per import and adds all `ProcessImportChunk` jobs to it.
- **Storage:** `config/queue.php` → `queue.batching` (e.g. `job_batches` table on default DB).
- **Batch name:** `"User Import #{$importId}"`.
- **Callbacks:**
  - **then()** — when all chunk jobs succeed: set import status to `completed`, dispatch `ImportCompleted`.
  - **catch()** — when the batch fails (e.g. after retries): log error, set import status to `failed`, dispatch `ImportCompleted` with failed status.

Chunks are added after the batch is dispatched (empty batch first, then stream CSV and `$batch->add([...])`).

---

## Horizon (Workers)

- **Config:** `config/horizon.php`
- **Dashboard:** Route at `HORIZON_PATH` (default `horizon`). Access: `viewHorizon` gate — open in `local`, admin-only otherwise (`HorizonServiceProvider`).
- **Redis:** Horizon uses Redis for its own state (e.g. supervisors, failed jobs, metrics). Queue connection can be `database` or `redis` depending on `QUEUE_CONNECTION`.

**Worker defaults (supervisor-1)**

- `timeout`: 300 seconds
- `tries`: 3
- `memory`: 128 MB
- **Environments:** `local` / `production` can override (e.g. `maxProcesses`, `balanceMaxShift`, `balanceCooldown`).

**Trim / retention**

- Recent/pending/completed batches: 60 minutes.
- Failed/recent_failed: 10080 minutes (7 days).

---

## Retry and timeout behaviour

- **Timeout:** If a chunk job runs longer than `$timeout` (300s), Laravel marks it as failed and, if retries remain, the job is retried later.
- **Retries:** Up to `$tries` (3) attempts per job. After the last failure, the job is moved to the failed job store; the batch may then run `catch()` depending on how Laravel batch failure is configured.
- **Batch cancellation:** Jobs check `$this->batch()?->cancelled()` at the start of `handle()` and exit without processing if the batch was cancelled.

---

## Other queued jobs

- **UserCreatedNotification** — implements `ShouldQueue`; dispatched when a user is created. Uses the same default queue and Horizon workers.

---

## References

- `app/Jobs/ProcessImportChunk.php` — chunk job implementation
- `app/Core/Application/UseCases/User/ImportUsers/ImportUsersUseCase.php` — batch creation and chunk dispatch
- `config/queue.php` — queue connection and batching table
- `config/horizon.php` — worker and Horizon dashboard config
- `app/Providers/HorizonServiceProvider.php` — `viewHorizon` gate
