<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use App\Models\User;
use App\Models\DeletedUser;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use App\Models\UserFoldersCount;
use Tests\Traits\Requests2FACode;
use App\Models\UserBookmarksCount;
use App\Models\UserFavoritesCount;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Passport\Database\Factories\ClientFactory;

class DeleteUserAccountTest extends TestCase
{
    use Requests2FACode, WithFaker;

    protected static string $accessToken;
    protected static string $refreshToken;

    protected function deleteAccountResponse(array $parameters = [], array $headers = []): TestResponse
    {
        return $this->deleteJson(route('deleteUserAccount'), $parameters, $headers);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users', 'deleteUserAccount');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteAccountResponse()->assertUnauthorized();
    }

    public function testAttributesMustBeRequired(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteAccountResponse()->assertJsonValidationErrors(['password']);
    }

    public function testDeleteUser(): void
    {
        $user = UserFactory::new()->afterCreating(function (User $user) {
            UserBookmarksCount::create([
                'user_id' => $user->id,
                'count'   => 5,
            ]);

            UserFavoritesCount::create([
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

        $this->deleteAccountResponse(['password' => 'password'], [
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
            'two_fa_code' => (string)$this->get2FACode($user->username, 'password'),
        ])->assertOk();

        static::$accessToken =  $response->json('data.token.access_token');
        static::$refreshToken = $response->json('data.token.refresh_token');
    }

    public function testPasswordMustMatchUserPassword(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteAccountResponse(['password' => 'or 1=1'])->assertUnauthorized();
    }

    public function testCanCreateNewAccountWithDeletedAccountEmail(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->deleteAccountResponse(['password' => 'password'])->assertOk();

        Passport::actingAsClient($client = ClientFactory::new()->asPasswordClient()->create());

        $this->postJson(route('createUser'), [
            'firstname' => $this->faker->firstName,
            'lastname'  => $this->faker->lastName,
            'username'  => UserFactory::randomUsername(),
            'email' => $user->email,
            'password'  => $password = Str::random(7) . rand(0, 9),
            'password_confirmation' => $password,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'grant_type' => 'password',
        ])->assertCreated();
    }
}
