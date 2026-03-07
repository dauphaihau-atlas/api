<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->insert([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'description' => 'Platform-wide administration access',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'super_admin')->delete();
    }
};
