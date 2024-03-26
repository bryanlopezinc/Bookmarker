<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Favorite;
use App\Repositories\FavoriteRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RemoveBookmarksFromFavoritesTest extends TestCase
{
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
        $this->loginUser(UserFactory::new()->create());

        $this->deleteFavoriteResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('bookmarks');

        $this->deleteFavoriteResponse(['bookmarks' => '1,1,3,4,5',])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
            ]);

        $this->deleteFavoriteResponse(['bookmarks' => collect()->times(51)->implode(',')])
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

        $this->deleteFavoriteResponse(['bookmarks' => (string)$bookmark->id])->assertOk();

        $this->assertDatabaseMissing(Favorite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id'     => $user->id
        ]);
    }

    public function testDeleteMultipleFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $ids = BookmarkFactory::new()
            ->count(3)
            ->for($user)
            ->create()
            ->pluck('id')
            ->all();

        (new FavoriteRepository())->createMany($ids, $user->id);

        $this->deleteFavoriteResponse(['bookmarks' => implode(',', [$ids[0], $ids[1]])])->assertOk();

        $this->assertDatabaseHas(Favorite::class, [
            'bookmark_id' => $ids[2],
            'user_id' => $user->id
        ]);
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExistsInFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->loginUser(UserFactory::new()->create());

        $this->deleteFavoriteResponse(['bookmarks' => (string) $bookmark->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
