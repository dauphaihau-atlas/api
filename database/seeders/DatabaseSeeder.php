<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenant = TenantModel::query()->updateOrCreate([
            'slug' => 'default',
        ], [
            'name' => 'Arc',
            'settings' => null,
            'is_active' => true,
        ]);

        $adminUser = UserModel::query()->updateOrCreate([
            'email' => 'maya.chen@arc.test',
        ], [
            'name' => 'Maya Chen',
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => '2024-03-01 09:00:00',
            'updated_at' => '2024-03-01 09:00:00',
        ]);
        $this->syncRole($adminUser, 'tenant_owner');

        $this->call(UserExportDemoSeeder::class);

        $superAdminUser = UserModel::query()->updateOrCreate([
            'email' => 'superadmin@example.com',
        ], [
            'name' => 'Super Admin',
            'tenant_id' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
        $this->syncRole($superAdminUser, 'super_admin');
    }

    private function syncRole(UserModel $user, string $roleSlug): void
    {
        $roleId = RoleModel::query()
            ->where('slug', $roleSlug)
            ->value('id');

        if ($roleId === null) {
            return;
        }

        $user->roles()->syncWithoutDetaching([$roleId]);
    }
}
