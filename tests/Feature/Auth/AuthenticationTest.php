<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => 'login',
            'status' => 'success',
        ]);
    }

    public function test_hr_super_admin_can_authenticate_with_case_insensitive_email(): void
    {
        $user = User::factory()->create([
            'email' => 'hr.admin@example.com',
            'role' => 'admin',
            'admin_type' => 'hr',
            'password' => \Illuminate\Support\Facades\Hash::make('HrPassword123!'),
        ]);

        $response = $this->post('/login', [
            'email' => ' HR.ADMIN@EXAMPLE.COM ',
            'password' => 'HrPassword123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_finance_head_can_authenticate_with_case_insensitive_email(): void
    {
        $user = User::factory()->create([
            'email' => 'finance.head@example.com',
            'role' => 'finance_head',
            'password' => \Illuminate\Support\Facades\Hash::make('FinanceHead123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'FINANCE.HEAD@EXAMPLE.COM',
            'password' => 'FinanceHead123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => 'login',
            'status' => 'failed',
        ]);
    }

    public function test_five_failed_login_attempts_lock_the_account_for_three_minutes(): void
    {
        $user = User::factory()->create();
        $credentials = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        foreach (range(1, 4) as $attempt) {
            $this->post('/login', $credentials)->assertRedirect();
        }

        $response = $this->from('/login')->followingRedirects()->post('/login', $credentials);
        $response->assertOk();
        $response->assertSee('id="loginLockoutCountdown"', false);
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHas('login_lockout_seconds');
        $this->assertGuest();

        $this->travel(181)->seconds();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'event' => 'logout',
            'status' => 'success',
        ]);
    }
}
