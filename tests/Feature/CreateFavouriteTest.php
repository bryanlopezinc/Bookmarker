<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favourite;
use App\Models\UserFavouritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class CreateFavouriteTest extends TestCase
{
    use WillCheckBookmarksHealth;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createFavourite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/favourites', 'createFavourite');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenAttrbutesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('bookmarks');
        $this->getTestResponse(['bookmarks'])->assertJsonValidationErrorFor('bookmarks');
    }

    public function testCannotAddMoreThan_50_BookmarksSimultaneouslyToFavourites(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => collect()->times(51)->implode(',')])
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

        $this->getTestResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testWillAddBookmarkToFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->assertDatabaseHas(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testCanAddMultipleBookmarksToFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count($amount = 5)->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $bookmarks->each(function (Bookmark $bookmark) use ($user) {
            $this->assertDatabaseHas(Favourite::class, [
                'bookmark_id' => $bookmark->id,
                'user_id' => $user->id
            ]);
        });

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => $amount,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id]);

        $this->getTestResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testReturnErrorWhenBookmarkExistsInFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['bookmarks' => (string) $bookmark->id])->assertCreated();
        $this->getTestResponse(['bookmarks' => (string) $bookmark->id])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => "Bookmarks already exists in favourites"
            ]);
    }

    public function testReturnErrorWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $this->getTestResponse(['bookmarks' => (string) $bookmark->id])->assertForbidden();
    }

    public function testWillNotAddInvalidBookmarkToFavourite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['bookmarks' => (string) ($bookmark->id + 1)])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists"
            ]);

        $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);
    }
}
