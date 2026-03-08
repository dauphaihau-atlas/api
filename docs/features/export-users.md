# Export Users Feature

Export all users as a CSV file with signed download URLs. The export is generated synchronously and cached for 15 minutes per admin user.

---

## Overview

| Concern | Implementation |
|--------|-----------------|
| Use case | `App\Core\Application\UseCases\User\ExportUsers\ExportUsersUseCase` |
| CSV format | Header: `id`, `name`, `email`, `roles`, `created_at` |
| Storage disk | `config('filesystems.exports_disk', 'local')` — defaults to `local` |
| File path | `exports/users-{Y-m-d_His}.csv` |
| URL expiry | 15 minutes (signed URLs for non-local disks) |
| Caching | Last export metadata cached per admin for 15 minutes |
| Authorization | Admin role required (`users.export` permission) |

---

## Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Client: GET /api/v1/users/export                                           │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  auth:sanctum + AuthorizeUser:export middleware                             │
│    └─ Validate admin role via policy                                        │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  UserController::export()                                                   │
│    └─ ExportUsersUseCase::execute(ExportUsersRequest)                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  ExportUsersUseCase                                                         │
│    ├─ Load all users via UserRepository                                     │
│    ├─ Build CSV content (header + rows with escaping)                       │
│    └─ Write to storage disk (exports/users-{timestamp}.csv)                 │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  UserController::export() (continued)                                       │
│    ├─ Generate URL::temporarySignedRoute('export.download', 15 min)         │
│    └─ Cache metadata (path, url, expires_at) — key: exports:users:last:{id}│
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Client: GET /api/v1/users/export/download?path=...&signature=...          │
│    └─ ValidateSignature middleware verifies request (no auth required)      │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  UserController::downloadExport()                                           │
│    ├─ Validate path (must start with exports/, no path traversal)           │
│    ├─ Check file exists on disk                                             │
│    └─ Stream CSV file from storage (Content-Type: text/csv)                 │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

### 1. Generate Export

**Request**
```
GET /api/v1/users/export
Authorization: Bearer {token}
```

**Response (200)**
```json
{
  "path": "exports/users-2025-01-15_143022.csv",
  "url": "http://localhost/api/v1/users/export/download?path=exports/users-2025-01-15_143022.csv&signature=abcd1234...",
  "expires_at": "2025-01-15T14:45:22+00:00"
}
```

**Error (503) — Storage write failed**
```json
{
  "message": "Failed to generate export",
  "code": "EXPORT_GENERATION_FAILED"
}
```

---

### 2. Download Export

**Request**
```
GET /api/v1/users/export/download?path=exports/users-2025-01-15_143022.csv&expires=...&signature=abcd1234...
```

No `Authorization` header required — the signed URL is self-authenticating.

**Response (200)**
- Content-Type: `text/csv`
- Body: CSV file stream

**Error (404) — File not found**
```json
{
  "message": "Export file not found."
}
```

**Error (422) — Invalid path**
```json
{
  "message": "Invalid or missing path."
}
```

---

### 3. Last Export Metadata

**Request**
```
GET /api/v1/users/export/last
Authorization: Bearer {token}
```

**Response (200) — With cached export**
```json
{
  "data": {
    "path": "exports/users-2025-01-15_143022.csv",
    "url": "http://localhost/api/v1/users/export/download?path=...",
    "expires_at": "2025-01-15T14:45:22+00:00"
  }
}
```

**Response (200) — No recent export or cache expired**
```json
{
  "data": null
}
```

---

## CSV Format

Header row:
```
id,name,email,roles,created_at
```

Example data:
```
1,John Doe,john@example.com,admin|moderator,2025-01-10 08:30:15
2,Jane Smith,jane@example.com,user,2025-01-12 14:22:00
```

**Field escaping:**
- Fields containing `"`, `,`, newlines, or carriage returns are double-quoted and internal quotes are escaped with `""`.
- Example: `"Smith, John ""Jr."""`

---

## Use Case: ExportUsersUseCase

**Location:** `app/Core/Application/UseCases/User/ExportUsers/ExportUsersUseCase.php`

**Dependencies**
- `UserRepositoryInterface` — load all users with full data

**Key methods**

- **execute(ExportUsersRequest)** → ExportUsersResponse
  - Loads all users from repository
  - Builds CSV content with proper escaping
  - Writes to configured storage disk
  - Returns path only; URL generation is handled by the controller
  - Logs all steps

- **buildCsvContent()** → string
  - Builds CSV with header + user rows
  - Each row: `id`, `name`, `email`, `roles` (pipe-separated), `created_at` (Y-m-d H:i:s)

- **userToCsvRow(User)** → array
  - Converts domain User entity to CSV row array
  - Joins multiple roles with `|` separator

- **escapeCsvField(string)** → string
  - Escapes fields with quotes if they contain special characters
  - Handles RFC 4180 CSV format

---

## Storage Configuration

All disks (local and remote) use the same download mechanism: a Laravel `temporarySignedRoute` pointing to the `/export/download` proxy endpoint. The controller streams the file from storage internally, so the client never accesses the storage backend directly.

**Config:**
```php
// config/filesystems.php
'exports_disk' => env('EXPORTS_DISK', 'local'),
```

**MinIO note:** When using MinIO in Docker, set `MINIO_TEMPORARY_URL=http://localhost:9000` so the `temporary_url` disk config reflects the public hostname. This is not used for download URLs (which go through the Laravel proxy), but is available for other storage operations.

---

## Caching

**Last export metadata**
- Key: `exports:users:last:{adminId}`
- Value: `['path' => '...', 'url' => '...', 'expires_at' => '...']`
- TTL: 15 minutes (same as URL expiry)
- Purpose: Let client quickly fetch the last export without re-generating

---

## Authorization

**Policy:** `App\Presentation\Http\Policies\UserPolicy::export()`
- Only users with admin role can export

**Middleware:** `AuthorizeUser:export`
- Applied to `GET /users/export` and `GET /users/export/last`
- Checks policy before controller execution

**Download route:** `GET /users/export/download`
- Public route — no Sanctum auth required
- Protected by Laravel signed URL signature (`signed` middleware)
- Route is defined outside the `auth:sanctum` group in `routes/api/v1.php`

**Permission:** `users.export`
- Defined in migrations / permission seed

---

## Error Handling

**Storage write failure** → `RuntimeException` caught in controller
- Wrapped as `ServiceUnavailableException` (HTTP 503)
- Logged with error details

**Invalid download path** → `ValidationException`
- Path must start with `exports/` and cannot contain `..`
- Prevents path traversal attacks

**File not found** → `NotFoundException`
- Checked via `$disk->exists($path)`

---

## Logging

All operations are logged to `storage/logs/laravel.log`:

```php
Log::info('ExportUsers: start', ['path' => $path, 'disk' => $diskName]);
Log::info('ExportUsers: CSV content built', ['path' => $path, 'content_bytes' => $contentBytes]);
Log::info('ExportUsers: users loaded', ['count' => $count]);
Log::info('ExportUsers: file written', ['path' => $path, 'written_bytes' => $written]);
Log::info('ExportUsers: complete', ['path' => $path]);
```

---

## References

- `app/Core/Application/UseCases/User/ExportUsers/ExportUsersUseCase.php` — use case implementation
- `app/Core/Application/UseCases/User/ExportUsers/ExportUsersRequest.php` — DTO
- `app/Core/Application/UseCases/User/ExportUsers/ExportUsersResponse.php` — DTO
- `app/Presentation/Http/Controllers/Api/V1/UserController.php` — endpoints (export, downloadExport, lastExport)
- `app/Presentation/Http/Policies/UserPolicy.php` — authorization policy
- `config/filesystems.php` — storage disk configuration