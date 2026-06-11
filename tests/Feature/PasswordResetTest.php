<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $email): User
    {
        $role = Role::query()->where('name', 'Stagiaire')->firstOrFail();

        return User::query()->create([
            'full_name' => 'Test User',
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_reset_link_is_sent_only_when_email_matches(): void
    {
        Notification::fake();
        $user = $this->user('match@test.local');

        // Unknown email: neutral response, no link, no token.
        $this->post(route('forgot.password.perform'), ['email' => 'unknown@test.local'])
            ->assertRedirect();
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@test.local']);

        // Matching email: link sent + token stored.
        $this->post(route('forgot.password.perform'), ['email' => 'match@test.local'])
            ->assertRedirect()
            ->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPasswordLink::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'match@test.local']);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = $this->user('reset@test.local');
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@test.local',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset@test.local',
            'password' => 'nouveauMotDePasse1',
            'password_confirmation' => 'nouveauMotDePasse1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nouveauMotDePasse1', $user->fresh()->password_hash));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset@test.local']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = $this->user('bad@test.local');
        DB::table('password_reset_tokens')->insert([
            'email' => 'bad@test.local',
            'token' => Hash::make('the-real-token'),
            'created_at' => now(),
        ]);

        $this->post(route('password.update'), [
            'token' => 'wrong-token',
            'email' => 'bad@test.local',
            'password' => 'nouveauMotDePasse1',
            'password_confirmation' => 'nouveauMotDePasse1',
        ])->assertSessionHas('error');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password_hash));
    }
}
