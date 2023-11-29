<?php

namespace Tests\Feature\User;

use App\Filesystem\ProfileImageFileSystem;
use App\Repositories\FavoriteRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchAuthUserProfileTest extends TestCase
{
    protected function getUserProfileResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('authUserProfile'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/me', 'authUserProfile');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->getUserProfileResponse()->assertUnauthorized();
    }

    public function testFetchUserProfile(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->getUserProfileResponse()
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonCount(8, 'data.attributes')
            ->assertJson(function (AssertableJson $json) use ($user) {
                $json->where('data.attributes.name', "{$user->first_name} {$user->last_name}");
                $json->where('data.attributes.username', $user->username);
                $json->where('data.attributes.bookmarks_count', 0);
                $json->where('data.attributes.favorites_count', 0);
                $json->where('data.attributes.folders_count', 0);
                $json->where('data.attributes.has_verified_email', true);
                $json->where('data.attributes.profile_image_url', (new ProfileImageFileSystem)->publicUrl($user->profile_image_path));
                $json->etc();
            })
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'attributes' => [
                        'id',
                        'name',
                        'username',
                        'bookmarks_count',
                        'favorites_count',
                        'folders_count',
                        'has_verified_email',
                        'profile_image_url'
                    ],
                ]
            ]);
    }

    public function testWillReturnCorrectFavoritesCountValueWhenBookmarksDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        (new FavoriteRepository)->createMany($bookmarks->pluck('id')->all(), $user->id);

        $this->getUserProfileResponse()
            ->assertOk()
            ->assertJsonPath('data.attributes.favorites_count', 2);

        $bookmarks->first()->delete();

        $this->getUserProfileResponse()
            ->assertOk()
            ->assertJsonPath('data.attributes.favorites_count', 1);
    }
}
