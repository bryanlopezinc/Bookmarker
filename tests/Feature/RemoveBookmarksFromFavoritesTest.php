<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\UserFavoritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
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

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteFavoriteResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFavoriteResponse()->assertJsonValidationErrorFor('bookmarks');
    }

    public function testAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFavoriteResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testWillDeleteFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->postJson(route('createFavorite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->deleteFavoriteResponse(['bookmarks' => (string)$bookmark->id])->assertOk();

        $this->assertDatabaseMissing(Favorite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserFavoritesCount::TYPE
        ]);
    }

    public function testCanDeleteMultipleFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()
            ->count(10)
            ->for($user)
            ->create()
            ->pluck('id')
            ->each(fn (int $bookmarkID) => $this->postJson(route('createFavorite'), ['bookmarks' => (string) $bookmarkID])->assertCreated());

        $deleteBookmarks = $bookmarkIDs->take(5);

        $this->deleteFavoriteResponse(['bookmarks' => $deleteBookmarks->implode(',')])->assertOk();

        $deleteBookmarks->each(fn (int $bookmarkID) => $this->assertDatabaseMissing(Favorite::class, [
            'bookmark_id' => $bookmarkID,
            'user_id' => $user->id
        ]));

        $bookmarkIDs->take(-5)->each(fn (int $bookmarkID) => $this->assertDatabaseHas(Favorite::class, [
            'bookmark_id' => $bookmarkID,
            'user_id' => $user->id
        ]));

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count' => 5,
            'type' => UserFavoritesCount::TYPE
        ]);
    }

    public function test_cannot_delete_more_than_50_favorites_simultaneously(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFavoriteResponse(['bookmarks' => collect()->times(51)->implode(',')])
            ->assertJsonValidationErrors([
                'bookmarks' => [
                    'The bookmarks must not have more than 50 items.'
                ]
            ]);
    }

    public function testBookmarksMustExistInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->postJson(route('createFavorite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFavoriteResponse(['bookmarks' => (string) $bookmark->id])->assertNotFound();
    }

    public function testWillRemoveOnlyUserFavorites(): void
    {
        [$frank, $dan]= UserFactory::new()->count(2)->create();

        $franksBookmark = BookmarkFactory::new()->for($frank)->create();
        $dansBookmark = BookmarkFactory::new()->for($dan)->create();

        Passport::actingAs($frank);
        $this->postJson(route('createFavorite'), ['bookmarks' => (string) $franksBookmark->id])->assertCreated();

        Passport::actingAs($dan);
        $this->postJson(route('createFavorite'), ['bookmarks' => (string) $dansBookmark->id])->assertCreated();

        Passport::actingAs($frank);
        $this->deleteFavoriteResponse(['bookmarks' => (string)$franksBookmark->id])->assertOk();

        $this->assertDatabaseHas(Favorite::class, [
            'bookmark_id' => $dansBookmark->id,
            'user_id' => $dan->id
        ]);
    }
}
