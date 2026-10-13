<?php

namespace Tests\Feature\Admin;

use App\Mail\AccountCredentials;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class VerificationCredentialsEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_sends_credentials_that_match_the_saved_login_password(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);
        $password = 'BranchTempPassword123!';
        $user = User::factory()->create([
            'id_verification_status' => 'pending',
            'is_active' => false,
            'is_verified' => false,
            'pending_password' => $password,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.verifications'))
            ->post(route('admin.verify.approve', $user))
            ->assertRedirect(route('admin.verifications'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('approved', $user->id_verification_status);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNull($user->pending_password);
        Mail::assertSent(AccountCredentials::class, function (AccountCredentials $mail) use ($password, $user) {
            return $mail->password === $password
                && $mail->hasTo($user->email);
        });
    }

    public function test_gmail_quota_failure_keeps_credentials_available_for_admin_retry(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);
        $password = 'BranchTempPassword123!';
        $user = User::factory()->create([
            'id_verification_status' => 'pending',
            'is_active' => false,
            'is_verified' => false,
            'pending_password' => $password,
        ]);

        $pendingMail = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        Mail::shouldReceive('to')->once()->with($user->email)->andReturn($pendingMail);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(\Mockery::type(AccountCredentials::class))
            ->andThrow(new TransportException('550-5.4.5 Daily user sending limit exceeded'));

        $this->actingAs($admin)
            ->from(route('admin.verifications'))
            ->post(route('admin.verify.approve', $user))
            ->assertRedirect(route('admin.verifications'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains(
                $message,
                'Gmail has reached its daily sending limit'
            ));

        $user->refresh();
        $this->assertSame('approved', $user->id_verification_status);
        $this->assertSame($password, $user->pending_password);
        $this->assertTrue(Hash::check($password, $user->password));

        $this->actingAs($admin)
            ->get(route('admin.verifications', ['tab' => 'credentials']))
            ->assertOk()
            ->assertSee('Credentials Not Sent')
            ->assertSee($user->email);
    }

    public function test_admin_can_retry_a_previously_failed_credentials_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'admin_type' => 'super_admin',
        ]);
        $password = 'BranchTempPassword123!';
        $user = User::factory()->create([
            'id_verification_status' => 'approved',
            'pending_password' => $password,
            'password' => Hash::make($password),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.verifications', ['tab' => 'credentials']))
            ->post(route('admin.verify.retry-credentials', $user))
            ->assertRedirect(route('admin.verifications', ['tab' => 'credentials']))
            ->assertSessionHas('success');

        $this->assertNull($user->fresh()->pending_password);
        Mail::assertSent(AccountCredentials::class);
    }
}
