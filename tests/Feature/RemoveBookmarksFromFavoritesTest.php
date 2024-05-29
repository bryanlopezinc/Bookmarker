<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Collections\BookmarkPublicIdsCollection;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Repositories\FavoriteRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class RemoveBookmarksFromFavoritesTest extends TestCase
{
    use GeneratesId;

    protected function deleteFavoriteResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFavorite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/favorites', 'deleteFavorite');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteFavoriteResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $bookmarksPublicIds = $this->generateBookmarkIds(51)->present();

        $this->loginUser(UserFactory::new()->create());

        $this->deleteFavoriteResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('bookmarks');

        $this->deleteFavoriteResponse(['bookmarks' => $bookmarksPublicIds->take(5)->add($bookmarksPublicIds[0])->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.5" => ["The bookmarks.5 field has a duplicate value."]
            ]);

        $this->deleteFavoriteResponse(['bookmarks' => $bookmarksPublicIds->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => [
                    'The bookmarks must not have more than 50 items.'
                ]
            ]);
    }

    public function testDeleteFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        (new FavoriteRepository())->create($bookmark->id, $user->id);

        $this->deleteFavoriteResponse(['bookmarks' => $bookmark->public_id->present()])->assertOk();

        $this->assertDatabaseMissing(Favorite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id'     => $user->id
        ]);
    }

    public function testDeleteMultipleFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        (new FavoriteRepository())->createMany($bookmarks->pluck('id')->all(), $user->id);

        $this->deleteFavoriteResponse(['bookmarks' => implode(',', [$bookmarksPublicIds[0], $bookmarksPublicIds[1]])])->assertOk();

        $this->assertDatabaseHas(Favorite::class, [
            'bookmark_id' => $bookmarks[2]->id,
            'user_id' => $user->id
        ]);
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExistsInFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->loginUser(UserFactory::new()->create());

        $this->deleteFavoriteResponse(['bookmarks' => $bookmark->public_id->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
