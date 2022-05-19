<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favourite;
use App\Models\UserResourcesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateFavouriteTest extends TestCase
{
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

    public function testCannotAddMoreThan_100_BookmarksSimultaneouslyToFavourites(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => collect()->times(101)->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => [
                    'cannot add more than 100 bookmarks simultaneously'
                ]
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

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserResourcesCount::FAVOURITES_TYPE
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

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count'   => $amount,
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ]);
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
                "could not add ids [{$bookmark->id}] because they have already been added to favourites"
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

        $this->getTestResponse(['bookmarks' => (string) $invalidID = ($bookmark->id + 1)])
            ->assertNotFound()
            ->assertExactJson([
                "could not add ids [$invalidID] because they do not exists"
            ]);

        $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);
    }
}
