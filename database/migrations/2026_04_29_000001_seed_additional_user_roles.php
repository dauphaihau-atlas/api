<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $roles = [
            ['name' => 'Tenant Owner', 'slug' => 'tenant_owner', 'description' => 'Owner-level tenant administration access'],
            ['name' => 'Support', 'slug' => 'support', 'description' => 'Support access for user assistance and audit review'],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only access'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                array_merge($role, ['updated_at' => $now, 'created_at' => $now]),
            );
        }

        DB::table('roles')
            ->where('slug', 'admin')
            ->update([
                'description' => 'Full tenant administration access',
                'updated_at' => $now,
            ]);

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        $rolePermissions = [
            'tenant_owner' => $permissionIds->keys()->all(),
            'admin' => $permissionIds->keys()->all(),
            'support' => [
                'users.view-any',
                'users.view',
                'users.create',
                'activity-logs.view-any',
                'activity-logs.view',
            ],
            'viewer' => [
                'users.view-any',
                'users.view',
                'activity-logs.view-any',
                'activity-logs.view',
            ],
        ];

        $pivotRows = [];
        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            foreach ($permissionSlugs as $permissionSlug) {
                if (! isset($roleIds[$roleSlug], $permissionIds[$permissionSlug])) {
                    continue;
                }
                $pivotRows[] = [
                    'permission_id' => $permissionIds[$permissionSlug],
                    'role_id' => $roleIds[$roleSlug],
                ];
            }
        }

        DB::table('permission_role')->insertOrIgnore($pivotRows);
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['tenant_owner', 'support', 'viewer'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('slug', ['tenant_owner', 'support', 'viewer'])->delete();
    }
};
