<?php

namespace Tests\Feature;

use App\Models\User;
use App\ValueObjects\Username;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\{Arr, Str};
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use WithFaker;

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

    public function testWillCreateUser(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        $this->getTestResponse([
            'firstname' => $this->faker->firstName,
            'lastname'  => $this->faker->lastName,
            'username'  => $username = Str::random(Username::MAX - 2) . '_' . rand(0, 9),
            'email'     => $mail = $this->faker->safeEmail,
            'password'  => $password = str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password'
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
    }
}
