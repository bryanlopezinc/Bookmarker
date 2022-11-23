<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\UserBookmarksCount;
use App\Models\UserFavoritesCount;
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

    protected function deleteBookmarksResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmarksFromSource'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/bookmarks/source', 'deleteBookmarksFromSource');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteBookmarksResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteBookmarksResponse()->assertJsonValidationErrorFor('source_id');
    }

    public function testWillDeleteBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get();

        [$firstBookmark, $secondBookmark] = [$userBookmarks->first(), $userBookmarks->last()];

        $this->deleteBookmarksResponse(['source_id' => $firstBookmark->source_id])->assertOk();

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

        $userBookmarks = BookmarkFactory::new()->for($user)->count(2)->create();

        $this->deleteBookmarksResponse(['source_id' => $userBookmarks->first()->source_id])->assertOk();

        $this->assertBookmarksHealthWillNotBeChecked([$userBookmarks->first()->id]);
    }

    public function testWillDeleteFavoritesWhenBookmarkIsDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get();

        [$firstBookmark, $secondBookmark] = [$userBookmarks->first(), $userBookmarks->last()];

        //Add created bookmarks to favorites.
        $this->postJson(route('createFavorite'), ['bookmarks' => (string) $firstBookmark->id])->assertCreated();
        $this->postJson(route('createFavorite'), ['bookmarks' => (string) $secondBookmark->id])->assertCreated();

        $this->deleteBookmarksResponse(['source_id' => $firstBookmark->source_id])->assertOk();

        $this->assertDatabaseMissing(Favorite::class, [
            'user_id' => $user->id,
            'bookmark_id' => $firstBookmark->id
        ]);

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserFavoritesCount::TYPE
        ]);
    }
}
