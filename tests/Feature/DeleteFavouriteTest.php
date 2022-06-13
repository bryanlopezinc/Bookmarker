<?php

namespace Tests\Feature;

use App\Models\Favourite;
use App\Models\UserFavouritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteFavouriteTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFavourite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/favourites', 'deleteFavourite');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('bookmarks');
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

    public function testWillDeleteFavourite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->getTestResponse(['bookmarks' => (string)$bookmark->id])->assertOk();

        $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testCanDeleteMultipleFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ])->pluck('id')
            ->each(fn (int $bookmarkID) => $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmarkID])->assertCreated());

        $deleteBookmarks = $bookmarkIDs->take(5);

        $this->getTestResponse(['bookmarks' => $deleteBookmarks->implode(',')])->assertOk();

        $deleteBookmarks->each(fn (int $bookmarkID) => $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmarkID,
            'user_id' => $user->id
        ]));

        $bookmarkIDs->take(-5)->each(fn (int $bookmarkID) => $this->assertDatabaseHas(Favourite::class, [
            'bookmark_id' => $bookmarkID,
            'user_id' => $user->id
        ]));

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count' => 5,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testReturnErrorWhenUserDoesNotHaveFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => (string) $bookmark->id])->assertNotFound();
    }
}
