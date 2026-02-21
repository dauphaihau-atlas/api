<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Roles
        DB::table('roles')->insert([
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full system access', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user access', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

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

        // Assign all permissions to admin role
        $permissionIds = DB::table('permissions')->pluck('id');
        $pivotRows = $permissionIds->map(fn ($id) => ['permission_id' => $id, 'role_id' => $adminRoleId])->all();
        DB::table('permission_role')->insert($pivotRows);
    }

    public function down(): void
    {
        DB::table('permission_role')->delete();
        DB::table('permissions')->delete();
        DB::table('roles')->delete();
    }
};
