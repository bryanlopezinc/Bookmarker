<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
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
            'username'  => Str::random(Username::MAX - 2) . '_' . rand(0, 9),
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
                ->assertJsonCount(2, 'errors');
        }

        $this->getTestResponse(Arr::except($attributes, ['password_confirmation']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password confirmation does not match.'])
            ->assertJsonCount(2, 'errors');
    }

    public function testWillReturnValidationErrorsWhenUsernameAttrbuteIsInvalid(): void
    {
        $this->getTestResponse(['username' => UserFactory::new()->create()->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username has already been taken.']);

        $this->getTestResponse(['username' => Str::random(Username::MAX + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => sprintf('The username must not be greater than %s characters.', Username::MAX)]);

        $this->getTestResponse(['username' => Str::random(Username::MIN - 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => sprintf('The username must be at least %s characters.', Username::MIN)]);

        $this->getTestResponse(['username' => Str::random(Username::MIN - 1) . '!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'The username contains invalid characters']);
    }

    public function testWillReturnValidationErrorsWhenEmailAttrbuteIsInvalid(): void
    {
        $this->getTestResponse(['email' => UserFactory::new()->create()->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function testVerificationUrlMustBeValid(): void
    {
        $this->getTestResponse(['verification_url' => 'foo_bar'])->assertJsonValidationErrors([
            'verification_url' => ['The verification url must be a valid URL.']
        ]);

        $this->getTestResponse(['verification_url' => $this->faker->url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :signature placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?id=:id'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :signature placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?hash=:hash'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :signature placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?signature=:signature'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :expires placeholder",
                ]
            ]);

        $this->getTestResponse(['verification_url' => $this->faker->url . '?expires=:expires'])
            ->assertUnprocessable()
            ->assertJsonCount(3, 'errors.verification_url')
            ->assertJsonValidationErrors([
                'verification_url' => [
                    "The verification url attribute must contain :id placeholder",
                    "The verification url attribute must contain :hash placeholder",
                    "The verification url attribute must contain :signature placeholder",
                ]
            ]);
    }

    public function testWillCreateUser(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        Notification::fake();

        $response = $this->getTestResponse([
            'firstname' => $this->faker->firstName,
            'lastname'  => $this->faker->lastName,
            'username'  => $username = Str::random(Username::MAX - 2) . '_' . rand(0, 9),
            'email'     => $mail = $this->faker->safeEmail,
            'password'  => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
            'verification_url' => $this->faker->url . '?' .  http_build_query([
                'id' => ':id',
                'hash' => ':hash',
                'signature' => ':signature',
                'expires' => ':expires'
            ])
        ])
            ->assertCreated()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(6, 'data.attributes')
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
                        'folders_count'
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
                $assertableJson->where('data.attributes.bookmarks_count', 0)->etc();
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
        $components = $this->parseQuery(static::$verificationUrl);

        $uri = route('verification.verify', [
            $components['id'],
            $components['hash'],
            'signature' => $components['signature'],
            'expires' => $components['expires']
        ]);

        $this->getJson($uri, ['Authorization' => 'Bearer ' . static::$accessToken])->assertOk();

        $this->assertTrue(User::whereKey($components['id'])->sole()->email_verified_at->isToday());
    }

    private function parseQuery(string $url): array
    {
        $parts = parse_url($url);

        $result = [];

        foreach (explode('&', $parts['query']) as $query) {
            [$key, $value] = explode('=', $query);
            $result[$key] = $value;
        }

        return $result;
    }
}
