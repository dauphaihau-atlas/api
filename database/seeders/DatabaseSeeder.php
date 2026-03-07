<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenant = TenantModel::factory()->create([
            'name' => 'Default Tenant',
            'slug' => 'default',
        ]);

        UserModel::factory()->admin()->forTenant($tenant)->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        UserModel::factory(9)->user()->forTenant($tenant)->create();

        UserModel::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ]);
    }
}
