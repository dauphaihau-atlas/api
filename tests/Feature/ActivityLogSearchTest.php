<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_activity_logs_by_causer_name(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $admin->name = 'Admin Boss';
        $admin->save();
        $otherUser = UserModel::factory()->forTenant($tenant)->create(['name' => 'Charlie Delta']);

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'updated',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $otherUser->id,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=Charlie', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($otherUser->id, $response->json('data.0.causer_id'));
    }

    public function test_search_activity_logs_by_event(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'deleted',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=deleted', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('deleted', $response->json('data.0.event'));
    }

    public function test_search_activity_logs_returns_empty_for_no_match(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=nonexistent', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_search_user_activity_logs(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();
        $admin->name = 'Admin Boss';
        $admin->save();
        $targetUser = UserModel::factory()->forTenant($tenant)->create(['name' => 'Target User']);
        $otherCauser = UserModel::factory()->forTenant($tenant)->create(['name' => 'Other Causer']);

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => $targetUser->id,
            'causer_type' => UserModel::class,
            'causer_id' => $admin->id,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'updated',
            'subject_type' => UserModel::class,
            'subject_id' => $targetUser->id,
            'causer_type' => UserModel::class,
            'causer_id' => $otherCauser->id,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/users/'.$targetUser->id.'/activity-logs?search=Other', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($otherCauser->id, $response->json('data.0.causer_id'));
    }

    public function test_empty_search_returns_all_activity_logs(): void
    {
        ['tenant' => $tenant, 'admin' => $admin, 'token' => $token] = $this->createTenantWithAdmin();

        ActivityLogModel::query()->delete();

        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'created',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);
        ActivityLogModel::create([
            'tenant_id' => $tenant->id,
            'log_name' => 'default',
            'event' => 'updated',
            'subject_type' => UserModel::class,
            'subject_id' => 1,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => null,
        ]);

        $response = $this->getJson('/api/v1/activity-logs?search=', $this->tenantHeaders($tenant, $token));

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
    }
}
