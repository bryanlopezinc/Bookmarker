<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Tests\TestCase;
use Tests\Traits\ResquestsVerificationCode;

final class LogoutTest extends TestCase
{
    use ResquestsVerificationCode;

    protected static string $accessToken;
    protected static string $refreshToken;

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('logoutUser'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/logout', 'logoutUser');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillLogoutUser(): void
    {
        $user = UserFactory::new()->create();

        $this->setTokens($user);

        $this->getTestResponse(headers: ['Authorization' => 'Bearer ' . static::$accessToken])->assertOk();
    }

    private function setTokens(User $user): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        $response = $this->postJson(route('loginUser'), [
            'username'  => $user->username,
            'password'  => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
            'two_fa_code' => (string)$this->getVerificationCode($user->username, 'password'),
        ])->assertOk();

        static::$accessToken =  $response->json('data.token.access_token');
        static::$refreshToken = $response->json('data.token.refresh_token');
    }

    /**
     * @depends testWillLogoutUser
     */
    public function testWillRevokeTokens(): void
    {
        $client = ClientFactory::new()->asPasswordClient()->create();

        $this->getJson(route('authUserProfile'), $headers = [
            'Authorization' => 'Bearer ' . static::$accessToken
        ])->assertUnauthorized();

        $this->postJson(route('refreshToken'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => static::$refreshToken,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
        ], $headers)->assertUnauthorized();
    }
}
