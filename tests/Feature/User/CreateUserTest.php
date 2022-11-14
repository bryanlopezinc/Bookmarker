<?php

namespace Tests\Feature\User;

use App\Models\SecondaryEmail;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\ValueObjects\Url;
use App\ValueObjects\Username;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use WithFaker;

    private static string $verificationUrl;

    protected function createUserResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createUser'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users', 'createUser');
    }

    public function testUnauthorizedClientCannotAccessRoute(): void
    {
        $this->createUserResponse()->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $attributes = [
            'firstname' => $this->faker->firstName,
            'lastname'  => $this->faker->lastName,
            'username'  => Str::random(Username::MAX_LENGTH - 2) . '_' . rand(0, 9),
            'email'     => $this->faker->safeEmail,
            'password'  => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password
        ];

        foreach (Arr::except($attributes, ['password_confirmation']) as $key => $attribute) {
            $params = $attributes;

            unset($params[$key]);

            $this->createUserResponse($params)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$key => "The {$key} field is required."])
                ->assertJsonCount(1, 'errors');
        }

        $this->createUserResponse(Arr::except($attributes, ['password_confirmation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password confirmation does not match.'])
            ->assertJsonCount(1, 'errors');
    }

    public function testUsernameMustBeValid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['username' => UserFactory::new()->create()->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username has already been taken.']);

        $this->createUserResponse(['username' => Str::random(Username::MAX_LENGTH + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => sprintf('The username must not be greater than %s characters.', Username::MAX_LENGTH)]);

        $this->createUserResponse(['username' => Str::random(Username::MIN_LENGTH - 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => sprintf('The username must be at least %s characters.', Username::MIN_LENGTH)]);

        $this->createUserResponse(['username' => Str::random(Username::MIN_LENGTH - 1) . '!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username contains invalid characters']);
    }

    public function testEmailMustBeValid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['email' => UserFactory::new()->create()->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testEmailMustNotBe_Existing_SecondaryEmail(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $user = UserFactory::new()->create();

        SecondaryEmail::query()->create([
            'email' => $userSecondaryEmail = UserFactory::new()->make()->email,
            'user_id' => $user->id,
            'verified_at' => now()
        ]);

        $this->createUserResponse(['email' => $userSecondaryEmail])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testWillCreateUser(): void
    {
        Passport::actingAsClient($client = ClientFactory::new()->asPasswordClient()->create());

        config(['settings.EMAIL_VERIFICATION_URL' => $this->faker->url . '?id=:id&hash=:hash&signature=:signature&expires=:expires']);

        Notification::fake();

        $this->createUserResponse([
            'firstname' => $firstname = $this->faker->firstName,
            'lastname'  => $lastname =  $this->faker->lastName,
            'username'  => $username = Str::random(Username::MAX_LENGTH - 2) . '_' . rand(0, 9),
            'email'     => $mail = $this->faker->safeEmail,
            'password'  => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
        ])->assertCreated();

        $user = User::query()->where('email', $mail)->sole();

        $this->assertEquals($username, $user->username);
        $this->assertEquals($firstname, $user->firstname);
        $this->assertEquals($lastname, $user->lastname);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            static::$verificationUrl =  $notification->toMail($user)->actionUrl;

            return true;
        });
    }

    /**
     * @depends testWillCreateUser
     */
    public function testCanVerifyEmailWithParameters(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $components = (new Url(static::$verificationUrl))->parseQuery();

        $uri = route('verification.verify', [
            $components['id'],
            $components['hash'],
            'signature' => $components['signature'],
            'expires' => $components['expires']
        ]);

        $this->getJson($uri)->assertOk();

        $this->assertTrue(User::whereKey($components['id'])->sole()->email_verified_at->isToday());
    }

    public function testFirstNameMustNotBeGreaterThan_100(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['firstname' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['firstname' => 'The firstname must not be greater than 100 characters.']);
    }

    public function testLastNameMustNotBeGreaterThan_100(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['lastname' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lastname' => 'The lastname must not be greater than 100 characters.']);
    }

    public function testPasswordMustBeAtLeast_8_characters(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['password' => 'secured'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must be at least 8 characters.']);
    }

    public function testPasswordMustContainOneNumber(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->createUserResponse(['password' => 'password_password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must contain at least one number.']);
    }
}
