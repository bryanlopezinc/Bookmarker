<?php

namespace Tests\Feature;

use App\Models\DeletedUser;
use App\Models\User;
use App\Models\UserBookmarksCount;
use App\Models\UserFavouritesCount;
use App\Models\UserFoldersCount;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\ResquestsVerificationCode;

class DeleteUserAccountTest extends TestCase
{
    use ResquestsVerificationCode;

    protected static string $accessToken;
    protected static string $refreshToken;

    protected function getTestResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->deleteJson(route('deleteUserAccount'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users', 'deleteUserAccount');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testAttributesMustBeRequired(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['password']);
    }

    public function testDeleteUser(): void
    {
        $user = UserFactory::new()->afterCreating(function (User $user) {
            UserBookmarksCount::create([
                'user_id' => $user->id,
                'count'   => 5,
            ]);

            UserFavouritesCount::create([
                'user_id' => $user->id,
                'count'   => 5,
            ]);

            UserFoldersCount::create([
                'user_id' => $user->id,
                'count'   => 5,
            ]);
        })->create();

        $this->setTokens($user);

        $userToken = Passport::token()->query()->where('user_id', $user->id)->sole();

        $this->getTestResponse(['password' => 'password'], [
            'Authorization' => 'Bearer ' . static::$accessToken
        ])->assertOk();

        $this->assertDatabaseMissing(User::class, ['id' => $user->id]);
        $this->assertDatabaseHas(DeletedUser::class, ['user_id' => $user->id]);
        $this->assertDatabaseMissing('users_resources_counts', ['user_id' => $user->id]);

        $this->assertDatabaseHas(Passport::tokenModel(), [
            'id' => $userToken->id,
            'user_id' => $user->id,
            'revoked' => 1
        ]);

        $this->assertDatabaseHas(Passport::refreshTokenModel(), [
            'access_token_id' => $userToken->id,
            'revoked' => 1
        ]);
    }

    /**
     * @depends testDeleteUser
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

    public function testPasswordMustMatchUserPassword(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['password' => 'or 1=1'])->assertUnauthorized();
    }
}
