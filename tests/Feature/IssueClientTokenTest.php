<?php

namespace Tests\Feature;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;

class IssueClientTokenTest extends TestCase
{
    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('issueClientToken'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/client/oauth/token', 'issueClientToken');
    }

    public function testWillReturnValidationErrorsWhenRequiredAttributesAreMissing(): void
    {
        $this->getTestResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id', 'client_secret', 'grant_type']);
    }

    public function testGrantTypeMustBeClientCredentials(): void
    {
        $this->getTestResponse([
            'client_id' => 8,
            'client_secret' => 'secret',
            'grant_type' => 'password'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grant_type' => 'Invalid grant type']);
    }

    public function testSuccessResponse(): void
    {
        $client = ClientFactory::new()->asClientCredentials()->create();

        $this->getTestResponse([
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'client_credentials'
        ])
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'expires_in'
            ])
            ->assertJson(function (AssertableJson $json) {
                $json->where('expires_in', function (int $expiresAt) {
                    $this->assertTrue(now()->addSeconds($expiresAt)->isNextMonth());

                    return true;
                });
                $json->etc();
            });
    }
}
