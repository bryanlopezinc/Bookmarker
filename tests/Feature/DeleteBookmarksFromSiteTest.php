<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favourite;
use App\Models\UserResourcesCount;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesBookmark;

class DeleteBookmarksFromSiteTest extends TestCase
{
    use CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmarksFromSite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/site', 'deleteBookmarksFromSite');
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

        $this->getTestResponse(['site_id' => $firstBookmark->site_id])->assertStatus(202);

        $this->assertModelMissing($firstBookmark);
        $this->assertModelExists($secondBookmark);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);
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

        $this->getTestResponse(['site_id' => $firstBookmark->site_id])->assertStatus(202);

        $this->assertDatabaseMissing(Favourite::class, [
            'user_id' => $user->id,
            'bookmark_id' => $firstBookmark->id
        ]);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ]);
    }
}
