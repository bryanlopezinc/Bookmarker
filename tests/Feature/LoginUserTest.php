<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;

class LoginUserTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('loginUser'), $parameters);
    }

    public function testWillReturnValidationErrorsWhenCredentialsAreInvalid(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        $user = UserFactory::new()->create();

        $this->getTestResponse([
            'username'  => $user->username,
            'password'  => 'wrongPassword',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password'
        ])->assertStatus(400);

        $this->getTestResponse([
            'username'  =>  UserFactory::randomUsername(),
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password'
        ])->assertStatus(400);

         $this->getTestResponse([
            'username'  => $user->username,
            'password'  => 'password',
        ])->assertStatus(400);
    }

    public function testWillLoginUser(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        $user = UserFactory::new()->create();

        $this->getTestResponse([
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password'
        ])
            ->assertSuccessful()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(5, 'data.attributes')
            ->assertJsonCount(4, 'data.token')
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'id',
                        'firstname',
                        'lastname',
                        'username',
                        'bookmarks_count'
                    ],
                    'token' => [
                        'token_type',
                        'expires_in',
                        'access_token',
                        'refresh_token'
                    ]
                ]
            ]);
    }
}
