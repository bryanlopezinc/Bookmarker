<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderBookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HideFolderBookmarksTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('hideFolderBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/bookmarks/hide', 'hideFolderBookmarks');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['folder_id', 'bookmarks']);
    }

    public function testBookmarksCannotExceed_30(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmarks' => collect()->times(31)->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => ['The bookmarks must not have more than 30 items.']
            ]);
    }

    public function testHideFolderBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $dontHideBookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $bookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $folder = FolderFactory::new()->afterCreating(fn (Folder $folder) => FolderBookmark::insert(
            $bookmarkIDs->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => true
            ])->all()
        ))->create([
            'user_id' => $user->id
        ]);

        $dontHideBookmarkIDs->tap(fn (Collection $collection) => FolderBookmark::insert(
            $collection->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => true
            ])->all()
        ));

        $this->getTestResponse([
            'folder_id' => $folder->id,
            'bookmarks' => $bookmarkIDs->implode(',')
        ])->assertOk();

        $bookmarkIDs->each(function (int $bookmarkID) use ($folder) {
            $this->assertDatabaseHas(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folder->id,
                'is_public' => false
            ]);
        });

        //Assert other folder bookmarks where not affected.
        $dontHideBookmarkIDs->each(function (int $bookmarkID) use ($folder) {
            $this->assertDatabaseHas(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folder->id,
                'is_public' => true
            ]);
        });
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->getTestResponse([
            'folder_id' => $folder->id,
            'bookmarks' => collect()->times(30)->implode(',')
        ])->assertForbidden();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'folder_id' => $folder->id + 1,
            'bookmarks' => collect()->times(30)->implode(',')
        ])->assertNotFound();
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExistsInFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ])->pluck('id');

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'folder_id' => $folder->id,
            'bookmarks' => $bookmarkIDs->map(fn (int $bookmarkID) => $bookmarkID + 1)->implode(',')
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists in folder"
            ]);
    }
}