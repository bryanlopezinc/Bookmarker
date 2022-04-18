<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class ResetPasswordTest extends TestCase
{
    private const NEW_PASSWORD = 'abcdef123';

    private static Client $client;
    private static User $user;

    /**
     * @beforeClass
     */
    public static function setUpSharedResources(): void
    {
        (new self)->setUp();

        static::$client = ClientFactory::new()->asPasswordClient()->create();
        static::$user = UserFactory::new()->create([]);
    }

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('resetPassword'), $parameters, $headers);
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->getTestResponse([])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient(static::$client);

        $this->getTestResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'token']);
    }

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient(static::$client);

        $this->getTestResponse([
            'email'  => 'non-existentUser@yahoo.com',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'token' => 'token'
        ])->assertNotFound();
    }

    public function testSuccessResponse(): void
    {
        $user = static::$user;

        Passport::actingAsClient(static::$client);

        $initialPassword = $user->password;

        $token = $this->postJson(route('requestPasswordResetToken'), [
            'email'  => $user->email,
        ])->assertSuccessful()->json('token');

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
        $client = static::$client;
        $user = static::$user;

        Mail::fake();

        //test with old password
        $this->postJson(route('loginUser'), [
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
        ])->assertStatus(400);

        $this->postJson(route('loginUser'), [
            'username'  => $user->username,
            'password'  => self::NEW_PASSWORD,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
        ])->assertSuccessful();
    }
}
