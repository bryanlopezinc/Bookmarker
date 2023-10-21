<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Repositories\FavoriteRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\AssertsBookmarkJson;
use Tests\Traits\WillCheckBookmarksHealth;

class FetchUserFavoritesTest extends TestCase
{
    use AssertsBookmarkJson, WillCheckBookmarksHealth, AssertValidPaginationData;

    protected function fetchUserFavoritesResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserFavorites', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/favorites', 'fetchUserFavorites');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchUserFavoritesResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserFavorites');
    }

    public function testFetchUserFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->fetchUserFavoritesResponse()->assertOk()->assertJsonCount(0, 'data');

        $bookmark = BookmarkFactory::new()->for($user)->create();

        (new FavoriteRepository)->create($bookmark->id, $user->id);

        $response = $this->fetchUserFavoritesResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmark->id)
            ->assertJsonPath('data.0.attributes.is_user_favorite', true)
            ->assertJsonStructure([
                "links" => [
                    "first",
                    "prev",
                ],
                "meta" => [
                    "current_page",
                    "path",
                    "per_page",
                    "has_more_pages",
                ]
            ]);

        $this->assertBookmarkJson($response->json('data.0'));
    }

    public function testWillSortByLatest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();

        (new FavoriteRepository)->create($bookmarks[2]->id, $user->id);
        (new FavoriteRepository)->create($bookmarks[0]->id, $user->id);
        (new FavoriteRepository)->create($bookmarks[1]->id, $user->id);

        $this->fetchUserFavoritesResponse()
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->id)
            ->assertJsonPath('data.1.attributes.id', $bookmarks[0]->id)
            ->assertJsonPath('data.2.attributes.id', $bookmarks[2]->id);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->for($user)->create();

        (new FavoriteRepository)->createMany($bookmarks->pluck('id')->all(), $user->id);

        $this->fetchUserFavoritesResponse()->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillReturnEmptyResponseWhenUserHasNoFavorites(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchUserFavoritesResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
