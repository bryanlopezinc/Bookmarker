<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\ValueObjects\Url;
use App\ValueObjects\Username;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use WithFaker;

    private static string $verificationUrl;
    private static string $accessToken;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createUser'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users', 'createUser');
    }

    public function testWillReturnValidationErrorsWhenRequiredAttrbutesAreMissing(): void
    {
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

            $this->getTestResponse($params)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$key => "The {$key} field is required."])
                ->assertJsonCount(1, 'errors');
        }

        $this->getTestResponse(Arr::except($attributes, ['password_confirmation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password confirmation does not match.'])
            ->assertJsonCount(1, 'errors');
    }

    public function testWillReturnValidationErrorsWhenUsernameAttrbuteIsInvalid(): void
    {
        $this->getTestResponse(['username' => UserFactory::new()->create()->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username has already been taken.']);

        $this->getTestResponse(['username' => Str::random(Username::MAX_LENGTH + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => sprintf('The username must not be greater than %s characters.', Username::MAX_LENGTH)]);

        $this->getTestResponse(['username' => Str::random(Username::MIN_LENGTH - 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => sprintf('The username must be at least %s characters.', Username::MIN_LENGTH)]);

        $this->getTestResponse(['username' => Str::random(Username::MIN_LENGTH - 1) . '!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username contains invalid characters']);
    }

    public function testWillReturnValidationErrorsWhenEmailAttrbuteIsInvalid(): void
    {
        $this->getTestResponse(['email' => UserFactory::new()->create()->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testWillCreateUser(): void
    {
        config(['settings.EMAIL_VERIFICATION_URL' => $this->faker->url . '?id=:id&hash=:hash&signature=:signature&expires=:expires']);

        $client = ClientFactory::new()->asPasswordClient()->create();

        Notification::fake();

        $response = $this->getTestResponse([
            'firstname' => $this->faker->firstName,
            'lastname'  => $this->faker->lastName,
            'username'  => $username = Str::random(Username::MAX_LENGTH - 2) . '_' . rand(0, 9),
            'email'     => $mail = $this->faker->safeEmail,
            'password'  => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
        ])
            ->assertCreated()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(7, 'data.attributes')
            ->assertJsonCount(4, 'data.token')
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'firstname',
                        'lastname',
                        'username',
                        'bookmarks_count',
                        'favourites_count',
                        'folders_count',
                        'has_verified_email'
                    ],
                    'token' => [
                        'token_type',
                        'expires_in',
                        'access_token',
                        'refresh_token'
                    ]
                ]
            ])
            ->assertJson(function (AssertableJson $assertableJson) {
                $assertableJson->etc()
                    ->where('data.attributes.has_verified_email', false)
                    ->where('data.attributes.favourites_count', 0)
                    ->where('data.attributes.folders_count', 0)
                    ->where('data.attributes.bookmarks_count', 0);
            });

        $this->assertDatabaseHas(User::class, [
            'email' => $mail,
            'username' => $username
        ]);

        $notifiable = User::where('email', $mail)->sole();

        Notification::assertSentTo(
            $notifiable,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification) use ($notifiable) {
                static::$verificationUrl =  $notification->toMail($notifiable)->actionUrl;

                return true;
            }
        );

        static::$accessToken = $response->json('data.token.access_token');
    }

    /**
     * @depends testWillCreateUser
     */
    public function testCanVerifyEmailWithParameters(): void
    {
        $components = (new Url(static::$verificationUrl))->parseQuery();

        $uri = route('verification.verify', [
            $components['id'],
            $components['hash'],
            'signature' => $components['signature'],
            'expires' => $components['expires']
        ]);

        $this->getJson($uri, ['Authorization' => 'Bearer ' . static::$accessToken])->assertOk();

        $this->assertTrue(User::whereKey($components['id'])->sole()->email_verified_at->isToday());
    }

    public function testFirstnameMustNotBeGreaterThan_100(): void
    {
        $this->getTestResponse(['firstname' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['firstname' => 'The firstname must not be greater than 100 characters.']);
    }

    public function testLastnameMustNotBeGreaterThan_100(): void
    {
        $this->getTestResponse(['lastname' => str_repeat('A', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lastname' => 'The lastname must not be greater than 100 characters.']);
    }

    public function testPasswordMustBeAtLeast_8_characters(): void
    {
        $this->getTestResponse(['password' => 'secured'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must be at least 8 characters.']);
    }

    public function testPasswordMustContainOneNumber(): void
    {
        $this->getTestResponse(['password' => 'password_password'])
        ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password must contain at least one number.']);
    }
}
