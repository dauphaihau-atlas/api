<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserExportDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = TenantModel::query()
            ->where('slug', 'default')
            ->firstOrFail();

        $roleId = RoleModel::query()
            ->where('slug', 'user')
            ->value('id');

        foreach ($this->exportDemoUsers() as $userData) {
            $user = UserModel::query()->updateOrCreate([
                'email' => $userData['email'],
            ], [
                ...$userData,
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]);

            if ($roleId !== null) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }
        }
    }

    /**
     * @return array<int, array{name: string, email: string, created_at: string, updated_at: string}>
     */
    private function exportDemoUsers(): array
    {
        return [
            [
                'name' => 'Export Boundary Start',
                'email' => 'export-boundary-start@example.com',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'name' => 'Export Winter User',
                'email' => 'export-winter@example.com',
                'created_at' => '2024-02-15 09:30:00',
                'updated_at' => '2024-02-15 09:30:00',
            ],
            [
                'name' => 'Export Spring User',
                'email' => 'export-spring@example.com',
                'created_at' => '2024-04-10 14:00:00',
                'updated_at' => '2024-04-10 14:00:00',
            ],
            [
                'name' => 'Export Midyear User',
                'email' => 'export-midyear@example.com',
                'created_at' => '2024-06-15 12:00:00',
                'updated_at' => '2024-06-15 12:00:00',
            ],
            [
                'name' => 'Export Summer User',
                'email' => 'export-summer@example.com',
                'created_at' => '2024-08-20 16:45:00',
                'updated_at' => '2024-08-20 16:45:00',
            ],
            [
                'name' => 'Export Autumn User',
                'email' => 'export-autumn@example.com',
                'created_at' => '2024-10-05 08:15:00',
                'updated_at' => '2024-10-05 08:15:00',
            ],
            [
                'name' => 'Export Boundary End',
                'email' => 'export-boundary-end@example.com',
                'created_at' => '2024-12-31 23:59:59',
                'updated_at' => '2024-12-31 23:59:59',
            ],
            [
                'name' => 'Export New Year User',
                'email' => 'export-new-year@example.com',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ],
        ];
    }
}
