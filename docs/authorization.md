# Authorization

This document describes how authorization is implemented in the admin dashboard API.

## Overview

- **Authentication**: Laravel Sanctum.
- **Authorization**: Role-based access control using roles, permissions, Laravel policies, and a small set of gates.
- **Tenant scope**: Tenant API routes require an authenticated user and resolved tenant context.
- **Default role**: New regular users receive `user` unless a creator assigns another allowed role.
- **Enforcement point**: API policies and request validation are the source of truth. UI filtering is only a convenience.

## Current Roles

| Role | Description |
|------|-------------|
| `user` | Standard tenant user. Can access own profile and normal authenticated tenant context. |
| `viewer` | Read-only access to users and activity logs. Good for auditors and support observers. |
| `support` | Can view users and activity logs and invite/create non-elevated users. Cannot delete users or assign elevated roles. |
| `admin` | Tenant admin. Can manage users, imports, exports, and activity logs. Cannot assign `admin` or `tenant_owner`. |
| `tenant_owner` | Highest tenant-level role. Can manage admins and destructive tenant user actions. Intended owner for future tenant settings and billing controls. |
| `super_admin` | Platform-level role outside normal tenant ownership. Can bypass tenant-level policy checks where explicitly allowed. |

## Planned Candidate Roles

These roles describe likely future product needs. They are not implemented until seeded, assigned permissions, covered by policies, and exposed through the assignable roles endpoint.

| Role | Intended use |
|------|--------------|
| `manager` | Can create/invite regular users and view exports, but cannot assign admin roles or force-delete. |
| `auditor` | Can access activity logs and exports, but has no mutation permissions. |
| `billing_admin` | Tenant billing and subscription management, if billing becomes part of the dashboard. |
| `developer` | API keys, webhooks, integrations, and technical tenant settings, if those areas are added. |

## Role Permissions

Permissions are stored in `permissions` and joined to roles through `permission_role`.

| Role | Permission intent |
|------|-------------------|
| `viewer` | `users.view-any`, `users.view`, `activity-logs.view-any`, `activity-logs.view` |
| `support` | Viewer permissions plus `users.create` |
| `admin` | Full tenant user and activity-log administration |
| `tenant_owner` | Same broad tenant permissions as admin, plus elevated assignment authority |
| `super_admin` | Platform override where policies explicitly check for it |

The current policy layer still decides final access. Role-permission rows should be treated as inputs to policy decisions, not as a replacement for policy review.

## Assignable Roles

The Add User flow should fetch assignable roles from:

```http
GET /api/v1/roles/assignable
```

The endpoint is tenant-scoped, authenticated, and requires `users.create`. It returns only roles the current user may assign.

| Current user role | Returned roles |
|-------------------|----------------|
| `support` | `user`, `viewer`, `support` |
| `admin` | `user`, `viewer`, `support` |
| `tenant_owner` | `user`, `viewer`, `support`, `admin`, `tenant_owner` |
| `super_admin` | `user`, `viewer`, `support`, `admin`, `tenant_owner` |
| `viewer` / `user` | Forbidden because they cannot create users |

Example response:

```json
{
  "data": [
    {
      "slug": "user",
      "name": "User",
      "description": "Standard user access"
    }
  ]
}
```

The create-user request still validates elevated assignment server-side. Hiding a role in the UI does not grant or deny permission by itself.

## Protected Route Groups

| Route | Method | Auth | Authorization |
|-------|--------|------|---------------|
| `/api/v1/login` | POST | No | Public |
| `/api/v1/register` | POST | No | Public |
| `/api/v1/logout` | POST | Yes | Authenticated |
| `/api/v1/me` | GET | Yes | Authenticated tenant user |
| `/api/v1/roles/assignable` | GET | Yes | `users.create` |
| `/api/v1/users` | GET | Yes | `users.view-any` |
| `/api/v1/users` | POST | Yes | `users.create` |
| `/api/v1/users/import*` | GET/POST/DELETE | Yes | User import permissions |
| `/api/v1/users/export*` | GET | Yes | User export permissions |
| `/api/v1/users/{id}` | PATCH/DELETE | Yes | User update/delete permissions |
| `/api/v1/activity-logs*` | GET | Yes | Activity-log view permissions |

## HTTP Status Codes

- **401 Unauthorized**: Not authenticated or token is invalid.
- **403 Forbidden**: Authenticated but not allowed by policy, gate, or middleware.
- **422 Unprocessable Entity**: Request is structurally valid but violates validation rules, such as assigning an elevated role without authority.

## Adding A Role

To add a role safely:

1. Seed the role in the default role/permission migration or a forward migration.
2. Attach explicit permissions in `permission_role`.
3. Add a factory state if tests need to create users with that role.
4. Update policies only when the role changes behavior beyond existing permissions.
5. Decide whether the role is assignable through `GET /api/v1/roles/assignable`.
6. Add feature tests for policy access, assignable-role visibility, and create-user validation if the role can be assigned.
7. Update this document.

## Testing

- Use `UserModel::factory()->user()->create()` for standard users.
- Use `viewer()`, `support()`, `admin()`, `tenantOwner()`, or `superAdmin()` factory states for role-specific tests.
- Cover both direct policy behavior and route-level authorization for new protected routes.
