<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favourite;
use App\Models\UserBookmarksCount;
use App\Models\UserFavouritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;
use Tests\Traits\CreatesBookmark;

class DeleteBookmarksFromSourceTest extends TestCase
{
    use CreatesBookmark, WillCheckBookmarksHealth;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmarksFromSource'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/source', 'deleteBookmarksFromSource');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('site_id');
    }

    public function testWillDeleteBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get();

        [$firstBookmark, $secondBookmark] = [$userBookmarks->first(), $userBookmarks->last()];

        $this->getTestResponse(['site_id' => $firstBookmark->site_id])->assertOk();

        $this->assertModelMissing($firstBookmark);
        $this->assertModelExists($secondBookmark);

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserBookmarksCount::TYPE
        ]);
    }

    public function testBookmarksWillNotBeHealthChecked(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userBookmarks = BookmarkFactory::new()->count(2)->create(['user_id' => $user->id]);

        $this->getTestResponse(['site_id' => $userBookmarks->first()->site_id])->assertOk();

        $this->assertBookmarksHealthWillNotBeChecked([$userBookmarks->first()->id]);
    }

    public function testWillDeleteFavouritesWhenBookmarkIsDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get();

        [$firstBookmark, $secondBookmark] = [$userBookmarks->first(), $userBookmarks->last()];

        //Add created bookmarks to favourites.
        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $firstBookmark->id])->assertCreated();
        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $secondBookmark->id])->assertCreated();

        $this->getTestResponse(['site_id' => $firstBookmark->site_id])->assertOk();

        $this->assertDatabaseMissing(Favourite::class, [
            'user_id' => $user->id,
            'bookmark_id' => $firstBookmark->id
        ]);

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFavouritesCount::TYPE
        ]);
    }
}
