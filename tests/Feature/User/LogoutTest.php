<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class LogoutTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('passport:client --personal --no-interaction');
    }

    protected function logOutResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->postJson(route('logoutUser'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/logout', 'logoutUser');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->logOutResponse()->assertUnauthorized();
    }

    public function testLogoutUser(): void
    {
        $user = UserFactory::new()->create();

        $accessToken = $user->createToken('token')->accessToken;

        $this->logOutResponse(headers: ['Authorization' => "Bearer {$accessToken}"])->assertOk();
    }
}
