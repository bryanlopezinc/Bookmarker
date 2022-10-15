<?php

namespace Tests\Feature\User;

use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchAuthUserProfileTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('authUserProfile'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/me', 'authUserProfile');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testFetchUserProfile(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse()
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonCount(7, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($user) {
                $json->where('data.attributes.firstname', $user->firstname);
                $json->where('data.attributes.lastname', $user->lastname);
                $json->where('data.attributes.username', $user->username);
                $json->where('data.attributes.bookmarks_count', 0);
                $json->where('data.attributes.favorites_count', 0);
                $json->where('data.attributes.folders_count', 0);
                $json->where('data.attributes.has_verified_email', true);
                $json->etc();
            })
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'firstname',
                        'lastname',
                        'username',
                        'bookmarks_count',
                        'favorites_count',
                        'folders_count',
                        'has_verified_email',
                    ],
                ]
            ]);;
    }
}
