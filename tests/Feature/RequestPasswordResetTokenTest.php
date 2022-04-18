<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class RequestPasswordResetTokenTest extends TestCase
{
    private Client $client;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = ClientFactory::new()->asClientCredentials()->create();
        $this->user = UserFactory::new()->create();
    }

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('requestPasswordResetToken'), $parameters, $headers);
    }

    public function testWillReturnValidationErrorsWhenClientCredentialsAreInvalid(): void
    {
        $this->getTestResponse([
            'email'  => $this->user->email,
        ])->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAsClient($this->client);

        $this->getTestResponse([])->assertUnprocessable()->assertJsonValidationErrorFor('email');
    }

    public function testWillReturnErrorResponseWhenUserDoesNotExists(): void
    {
        Passport::actingAsClient($this->client);

        $this->getTestResponse([
            'email'  => 'non-existentUser@yahoo.com'
        ])->assertNotFound();
    }

    public function testSuccessResponse(): void
    {
        Passport::actingAsClient($this->client);

        $this->getTestResponse([
            'email'  => $this->user->email
        ])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'expires'
            ])
            ->assertJson(function (AssertableJson $json) {
                $json->where('expires', 60);
                $json->etc();
            });

        $this->assertDatabaseHas('password_resets', [
            'email' => $this->user->email,
        ]);
    }
}
