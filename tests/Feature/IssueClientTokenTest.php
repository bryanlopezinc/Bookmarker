<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;

class IssueClientTokenTest extends TestCase
{
    protected function issueTokenResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('issueClientToken'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/client/oauth/token', 'issueClientToken');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->issueTokenResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id', 'client_secret', 'grant_type']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->issueTokenResponse([
            'client_id'     => 8,
            'client_secret' => 'secret',
            'grant_type'    => 'password'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grant_type' => 'Invalid grant type']);
    }

    public function testIssueToken(): void
    {
        $client = ClientFactory::new()->asClientCredentials()->create();

        $this->issueTokenResponse([
            'client_id'     => $client->id,
            'client_secret' => $client->secret,
            'grant_type'    => 'client_credentials'
        ])
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('expires_in', function (int $expiresAt) {
                $this->assertTrue(now()->addSeconds($expiresAt)->isNextMonth());

                return true;
            })
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'expires_in'
            ]);
    }
}
