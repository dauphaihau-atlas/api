<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Roles
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full tenant administration access'],
            ['name' => 'Tenant Owner', 'slug' => 'tenant_owner', 'description' => 'Owner-level tenant administration access'],
            ['name' => 'Support', 'slug' => 'support', 'description' => 'Support access for user assistance and audit review'],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only access'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user access'],
        ];
        DB::table('roles')->insert(array_map(
            fn (array $role) => array_merge($role, ['created_at' => $now, 'updated_at' => $now]),
            $roles,
        ));

        // Permissions
        $permissions = [
            ['group' => 'users', 'slug' => 'users.view-any', 'name' => 'View all users'],
            ['group' => 'users', 'slug' => 'users.view', 'name' => 'View a user'],
            ['group' => 'users', 'slug' => 'users.create', 'name' => 'Create users'],
            ['group' => 'users', 'slug' => 'users.update', 'name' => 'Update users'],
            ['group' => 'users', 'slug' => 'users.delete', 'name' => 'Delete users'],
            ['group' => 'users', 'slug' => 'users.import', 'name' => 'Import users'],
            ['group' => 'users', 'slug' => 'users.cancel-import', 'name' => 'Cancel user import'],
            ['group' => 'users', 'slug' => 'users.export', 'name' => 'Export users'],
            ['group' => 'users', 'slug' => 'users.restore', 'name' => 'Restore deleted users'],
            ['group' => 'users', 'slug' => 'users.force-delete', 'name' => 'Permanently delete users'],
            ['group' => 'activity-logs', 'slug' => 'activity-logs.view-any', 'name' => 'View all activity logs'],
            ['group' => 'activity-logs', 'slug' => 'activity-logs.view', 'name' => 'View an activity log'],
        ];

        $rows = array_map(fn (array $p) => array_merge($p, ['created_at' => $now, 'updated_at' => $now]), $permissions);
        DB::table('permissions')->insert($rows);

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

        DB::table('permission_role')->insert($pivotRows);
    }

    public function down(): void
    {
        DB::table('permission_role')->delete();
        DB::table('permissions')->delete();
        DB::table('roles')->delete();
    }
};
