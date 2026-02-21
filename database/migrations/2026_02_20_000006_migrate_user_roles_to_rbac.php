<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')->pluck('id', 'slug');

        // Process in chunks to stay within PostgreSQL's 65535 parameter limit.
        // Each row has 2 params (role_id, user_id), so 500 rows = 1000 params — safely under the limit.
        DB::table('users')->select('id', 'role')->orderBy('id')->chunk(500, function ($users) use ($roles): void {
            $pivotRows = [];
            foreach ($users as $user) {
                $slug = $user->role ?? 'user';
                if (isset($roles[$slug])) {
                    $pivotRows[] = ['role_id' => $roles[$slug], 'user_id' => $user->id];
                }
            }
            if ($pivotRows !== []) {
                DB::table('role_user')->insertOrIgnore($pivotRows);
            }
        });
    }

    public function down(): void
    {
        DB::table('role_user')->delete();
    }
};
