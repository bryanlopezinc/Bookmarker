<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Traits\Requests2FACode;
use Laravel\Passport\Database\Factories\ClientFactory;

class ResetPasswordTest extends TestCase
{
    use Requests2FACode;

    private const NEW_PASSWORD = 'abcdef123';

    private static User $user;

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('resetPassword'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/password/reset', 'resetPassword');
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->getTestResponse([])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'token']);
    }

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse([
            'email'  => 'non-existentUser@yahoo.com',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'token' => 'token'
        ])->assertNotFound()->assertExactJson([
            'message' => 'User not found'
        ]);
    }

    public function testWillReturnErrorResponseWhenTokenIsInvalid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse([
            'email'  => UserFactory::new()->create([])->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'token' => 'token'
        ])->assertStatus(400)
            ->assertExactJson([
                'message' => 'Invalid reset token'
            ]);
    }

    public function testSuccessResponse(): void
    {
        Notification::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();
        static::$user = $user;
        $initialPassword = $user->password;
        $token = '';

        $this->postJson(route('requestPasswordResetToken'), ['email'  => $user->email])->assertOk();

        Notification::assertSentTo($user, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->getTestResponse([
            'email'  => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'token' => $token
        ])->assertOk();

        $this->assertNotSame(
            $initialPassword,
            $user->query()->where('id', $user->id)->first()->password
        );
    }

    /**
     * @depends testSuccessResponse
     */
    public function testUserCanLoginWithNewPasswordAfterReset(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();
        $user = static::$user;

        Mail::fake();

        //assert cannot login with old password
        $this->postJson(route('loginUser'), [
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
            'two_fa_code' => '12345',
        ])->assertStatus(400)
            ->assertExactJson([
                "error" => "invalid_grant",
                "error_description" => "The user credentials were incorrect.",
                "message" => "The user credentials were incorrect."
            ]);

        $this->postJson(route('loginUser'), [
            'username'  => $user->username,
            'password'  => self::NEW_PASSWORD,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
            'two_fa_code' => (string) $this->get2FACode($user->username, self::NEW_PASSWORD),
        ])->assertOk();
    }


    public function testPasswordMustBeAtLeast_8_characters(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse(['password' => 'secured'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must be at least 8 characters.']);
    }

    public function testPasswordMustContainOneNumber(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->getTestResponse(['password' => 'password_password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must contain at least one number.']);
    }
}
