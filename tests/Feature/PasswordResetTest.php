<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/lupa-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_gets_the_same_generic_response(): void
    {
        Notification::fake();

        $this->post('/lupa-password', ['email' => 'tidak-ada@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => '135790',
            'password_confirmation' => '135790',
        ])->assertRedirect('/masuk');

        $this->assertTrue(Hash::check('135790', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->from('/reset-password/token-palsu')
            ->post('/reset-password', [
                'token' => 'token-palsu',
                'email' => $user->email,
                'password' => '135790',
                'password_confirmation' => '135790',
            ])->assertSessionHasErrors('email');
    }

    public function test_reset_form_renders(): void
    {
        $this->get('/reset-password/abc123?email=uji@example.test')
            ->assertOk()
            ->assertSee('Atur ulang');
    }
}
