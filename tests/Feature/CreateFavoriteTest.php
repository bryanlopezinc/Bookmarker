<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Collections\BookmarkPublicIdsCollection;
use App\Models\Favorite;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;
use Tests\Traits\WillCheckBookmarksHealth;

class CreateFavoriteTest extends TestCase
{
    use WillCheckBookmarksHealth;
    use GeneratesId;

    protected function createFavoriteResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createFavorite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/bookmarks/favorites', 'createFavorite');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->createFavoriteResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $bookmarksPublicIds = $this->generateBookmarkIds(51)->present();

        $this->loginUser(UserFactory::new()->create());

        $this->createFavoriteResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('bookmarks');

        $this->createFavoriteResponse(['bookmarks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('bookmarks');

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => ['cannot add more than 50 bookmarks simultaneously']
            ]);

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds->take(5)->add($bookmarksPublicIds[0])->implode(',')])
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.5" => ["The bookmarks.5 field has a duplicate value."]
            ]);

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds->take(2)->add(33)->implode(',')])->assertJsonValidationErrors(['bookmarks.2']);
    }

    public function testAddFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds->implode(',')])->assertCreated();

        $favorites = Favorite::query()->where('user_id', $user->id)->get();

        $this->assertCount(2, $favorites);
        $this->assertEquals(
            $bookmarks->pluck('id')->sort()->all(),
            $favorites->pluck('bookmark_id')->sort()->all()
        );
    }

    public function testWillCheckBookmarksHealth(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->for($user)->create();

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds->implode(',')])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillReturnConflictWhenBookmarkExistsInFavorites(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $userBookmarks = BookmarkFactory::times(3)->for($user)->create();

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($userBookmarks)->present();

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds[0]])->assertCreated();

        $this->createFavoriteResponse(['bookmarks' => $bookmarksPublicIds->implode(',')])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'FavoritesAlreadyExists',
                'conflict' => [0 => $bookmarksPublicIds[0]]
            ]);
    }

    public function testWhenBookmarkDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $this->createFavoriteResponse(['bookmarks' => $bookmark->public_id->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExist(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFavoriteResponse(['bookmarks' => $this->generateBookmarkId()->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => "BookmarkNotFound"]);

        $this->assertDatabaseMissing(Favorite::class, ['user_id' => $user->id]);
    }
}
