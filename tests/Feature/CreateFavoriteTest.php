<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\UserFavoritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class CreateFavoriteTest extends TestCase
{
    use WillCheckBookmarksHealth;

    protected function createFavoriteResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createFavorite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/favorites', 'createFavorite');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->createFavoriteResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFavoriteResponse()->assertJsonValidationErrorFor('bookmarks');
        $this->createFavoriteResponse(['bookmarks'])->assertJsonValidationErrorFor('bookmarks');
    }

    public function testCannotAddMoreThan_50_BookmarksSimultaneouslyToFavorites(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFavoriteResponse(['bookmarks' => collect()->times(51)->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => [
                    'cannot add more than 50 bookmarks simultaneously'
                ]
            ]);
    }

    public function testAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFavoriteResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testWillAddBookmarkToFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->assertDatabaseHas(Favorite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFavoritesCount::TYPE
        ]);
    }

    public function testCanAddMultipleBookmarksToFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count($amount = 5)->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $bookmarks->each(function (Bookmark $bookmark) use ($user) {
            $this->assertDatabaseHas(Favorite::class, [
                'bookmark_id' => $bookmark->id,
                'user_id' => $user->id
            ]);
        });

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count'   => $amount,
            'type' => UserFavoritesCount::TYPE
        ]);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillReturnErrorResponseWhenBookmarkExistsInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])->assertCreated();
        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => "Bookmarks already exists in favorites"
            ]);
    }

    public function testBookmarksMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])->assertForbidden();
    }

    public function testWillNotAddInvalidBookmarkToFavorite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) ($bookmark->id + 1)])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists"
            ]);

        $this->assertDatabaseMissing(Favorite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);
    }
}
