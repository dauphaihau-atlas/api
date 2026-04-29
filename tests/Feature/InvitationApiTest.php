<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_invitation_returns_invited_user(): void
    {
        $token = Str::random(64);
        $user = UserModel::factory()->user()->create([
            'email' => 'invited@example.com',
            'name' => 'Invited User',
            'email_verified_at' => null,
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email->getValue(),
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/invitations/accept?'.http_build_query([
            'email' => $user->email->getValue(),
            'token' => $token,
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'invited@example.com');
        $response->assertJsonPath('data.name', 'Invited User');
    }

    public function test_accept_invitation_sets_password_and_consumes_token(): void
    {
        $token = Str::random(64);
        $user = UserModel::factory()->user()->create([
            'email' => 'accept@example.com',
            'email_verified_at' => null,
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email->getValue(),
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/invitations/accept', [
            'email' => $user->email->getValue(),
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'accept@example.com',
        ]);
    }

    public function test_accept_invitation_returns_404_for_invalid_token(): void
    {
        $user = UserModel::factory()->user()->create(['email' => 'bad-token@example.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email->getValue(),
            'token' => Hash::make(Str::random(64)),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/invitations/accept', [
            'email' => $user->email->getValue(),
            'token' => 'wrong-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(404);
    }
}
