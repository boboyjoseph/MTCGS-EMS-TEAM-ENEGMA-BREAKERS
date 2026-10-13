<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_mail_transport_failure_returns_a_friendly_error_instead_of_crashing(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new TransportException('SMTP unavailable'));

        $this->post('/forgot-password', ['email' => 'user@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'The email service temporarily refused the reset email. Please try again later or contact the system administrator if the problem continues.',
            ]);
    }

    public function test_gmail_daily_sending_limit_is_reported_clearly(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new TransportException(
                '550-5.4.5 Daily user sending limit exceeded'
            ));

        $this->post('/forgot-password', ['email' => 'user@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Gmail has reached its daily sending limit, so it refused this reset email. Please try again after the limit resets or contact the system administrator to configure another mail account.',
            ]);
    }

    public function test_recent_reset_request_shows_the_retry_wait_time(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andReturn(Password::RESET_THROTTLED);

        $this->post('/forgot-password', ['email' => 'user@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'A reset link was requested recently. Please wait at least one minute before requesting another.',
            ]);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_reset_email_uses_mtcgs_branding(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertSame('emails.password-reset', $mail->view);
            $this->assertStringContainsString('MTCGS', $mail->render());
            $this->assertStringContainsString('cid:mtcgs-logo@mtcgs-ems', $mail->render());
            $this->assertCount(1, $mail->callbacks);

            $symfonyMessage = new Email();
            $mail->callbacks[0]($symfonyMessage);

            $this->assertCount(1, $symfonyMessage->getAttachments());
            $this->assertSame('mtcgs-logo@mtcgs-ems', $symfonyMessage->getAttachments()[0]->getContentId());

            return true;
        });
    }
}
