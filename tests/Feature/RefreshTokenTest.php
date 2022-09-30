<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Tests\Traits\Resquests2FACode;

class RefreshTokenTest extends TestCase
{
    use Resquests2FACode;

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('refreshToken'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/token/refresh', 'refreshToken');
    }

    public function testWillRefreshTokens(): void
    {
        $user = UserFactory::new()->create();
        $client = ClientFactory::new()->asPasswordClient()->create();

        $refreshToken = $this->getToken($user, $client);

        $this->postJson(route('refreshToken'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
        ],)
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonStructure([
                'token_type',
                'expires_in',
                'access_token',
                'refresh_token'
            ]);
    }

    private function getToken(User $user, Client $client): string
    {
        Http::fake([
            'ip-api.com/*' => Http::response('{
                    "country": "Canada",
                    "city": "Montreal"
            }'),
        ]);

        $response = $this->postJson(route('loginUser'), [
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
            'two_fa_code' => (string)$this->get2FACode($user->username, 'password'),
            'with_ip' => '24.48.0.1',
            'with_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.3 Safari/605.1.15'
        ])->assertOk();

        return $response->json('data.token.refresh_token');
    }
}
