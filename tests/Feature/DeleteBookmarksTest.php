<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkTag;
use App\Models\Favourite;
use App\Models\UserResourcesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesBookmark;

class DeleteBookmarksTest extends TestCase
{
    use CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmark'), $parameters);
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

        $this->getTestResponse(['ids' => (string)$bookmark->id])->assertStatus(204);

        $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmark->id]);
        $this->assertDatabaseMissing(BookmarkTag::class, ['bookmark_id' => $bookmark->id]);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);
    }

    public function testWillDeleteFavouritesWhenBookmarkIsDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();

        $bookmark = Bookmark::query()->where('user_id', $user->id)->first();

        //Add created bookmark to favourites.
        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->getTestResponse(['ids' => (string)$bookmark->id])->assertStatus(204);

        $this->assertDatabaseMissing(Favourite::class, [
            'user_id' => $user->id,
            'bookmark_id' => $bookmark->id
        ]);

        //Assert favourites count was decremented.
        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ]);
    }

    public function testWillReturnSuccessResponseIfBookmarkDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['ids' => (string)($model->id + 1)])->assertStatus(204);
    }

    public function testWillReturnForbiddenWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->getTestResponse(['ids' => (string)$model->id])->assertForbidden();
    }
}
