<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use App\Models\User;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;

class ResetPasswordTest extends TestCase
{
    private const NEW_PASSWORD = 'abcdef123';

    protected function resetPasswordResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('resetPassword'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/password/reset', 'resetPassword');
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->resetPasswordResponse([])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->withRequestId()
            ->resetPasswordResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'token']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this
            ->withRequestId()
            ->resetPasswordResponse([
                'email'                 => 'non-existentUser@yahoo.com',
                'password'              => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
                'token'                 => 'token'
            ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    public function testWillReturnNotFoundResponseWhenTokenIsInvalid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->withRequestId()
            ->resetPasswordResponse([
                'email'                 => UserFactory::new()->create([])->email,
                'password'              => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
                'token'                 => 'token'
            ])->assertStatus(400)
            ->assertExactJson(['message' => 'InvalidResetToken']);
    }

    public function testResetPassword(): void
    {
        Notification::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();
        $initialPassword = $user->password;

        /** @var PasswordBroker */
        $broker = app(PasswordBroker::class);

        $broker->sendResetLink(['email' => $user->email], function (User $user, string $hash) use (&$token) {
            $token = $hash;
        });

        $this->withRequestId()
            ->resetPasswordResponse($query = [
                'email'                 => $user->email,
                'password'              => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
                'token'                 => $token
            ])->assertOk();

        //assert token will be deleted.
        $this->withRequestId()
            ->resetPasswordResponse($query)
            ->assertBadRequest()
            ->assertExactJson(['message' => 'InvalidResetToken']);

        $updatedUser = $user->query()->where('id', $user->id)->first(['password']);

        $this->assertNotSame(
            $initialPassword,
            $updatedUser->password
        );

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $updatedUser->password));
    }

    public function testWillReturnUnprocessableWhenNewPasswordIsLessThan_8_characters(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->withRequestId()
            ->resetPasswordResponse(['password' => 'secured'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must be at least 8 characters.']);
    }

    public function testWillReturnUnprocessableWhenNewPasswordDoesNotContainANumber(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->withRequestId()
            ->resetPasswordResponse(['password' => 'password_password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must contain at least one number.']);
    }
}
