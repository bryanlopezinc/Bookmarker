<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkTag;
use App\Models\Favourite;
use App\Models\UserBookmarksCount;
use App\Models\UserFavouritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\AssertsBookmarksWillBeHealthchecked;
use Tests\Traits\CreatesBookmark;

class DeleteBookmarksTest extends TestCase
{
    use CreatesBookmark, AssertsBookmarksWillBeHealthchecked;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks', 'deleteBookmark');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('ids');
    }

    public function testAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'ids' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "ids.0" => ["The ids.0 field has a duplicate value."],
            "ids.1" => ["The ids.1 field has a duplicate value."]
        ]);
    }

    public function test_cannot_delete_more_than_100_bookmarks_simultaneously(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['ids' => collect()->times(101)->implode(',')])
            ->assertJsonValidationErrorFor('ids')
            ->assertJsonValidationErrors([
                'ids' => [
                    'cannot delete more than 100 bookmarks in one request'
                ]
            ]);
    }

    public function testWillDeleteBookmark(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();

        $bookmark = Bookmark::query()->where('user_id', $user->id)->first();

        $this->getTestResponse(['ids' => (string)$bookmark->id])->assertOk();

        $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmark->id]);
        $this->assertDatabaseMissing(BookmarkTag::class, ['bookmark_id' => $bookmark->id]);

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserBookmarksCount::TYPE
        ]);
    }

    public function testWilNotCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();

        $bookmark = Bookmark::query()->where('user_id', $user->id)->first();

        $this->getTestResponse(['ids' => (string)$bookmark->id])->assertOk();

        $this->assertBookmarksHealthWillNotBeChecked([$bookmark->id]);
    }

    public function testWillDeleteFavouritesWhenBookmarkIsDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();

        $bookmark = Bookmark::query()->where('user_id', $user->id)->first();

        //Add created bookmark to favourites.
        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->getTestResponse(['ids' => (string)$bookmark->id])->assertOk();

        $this->assertDatabaseMissing(Favourite::class, [
            'user_id' => $user->id,
            'bookmark_id' => $bookmark->id
        ]);

        //Assert favourites count was decremented.
        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testWillReturnSuccessResponseIfBookmarkDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['ids' => (string)($model->id + 1)])->assertOk();
    }

    public function testWillReturnForbiddenWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->getTestResponse(['ids' => (string)$model->id])->assertForbidden();
    }
}
