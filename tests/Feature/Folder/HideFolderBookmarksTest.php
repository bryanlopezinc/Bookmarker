<?php

namespace Tests\Feature\Folder;

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
    protected function hideFolderResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('hideFolderBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/bookmarks/hide', 'hideFolderBookmarks');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->hideFolderResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->hideFolderResponse()->assertJsonValidationErrors(['folder_id', 'bookmarks']);
    }

    public function testAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->hideFolderResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testBookmarksCannotExceed_50(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->hideFolderResponse(['bookmarks' => collect()->times(51)->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => ['The bookmarks must not have more than 50 items.']
            ]);
    }

    public function testHideFolderBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $dontHideBookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $bookmarkIDs = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');

        $folder = FolderFactory::new()
            ->for($user)
            ->afterCreating(fn (Folder $folder) => FolderBookmark::insert(
                $bookmarkIDs->map(fn (int $bookmarkID) => [
                    'folder_id' => $folder->id,
                    'bookmark_id' => $bookmarkID,
                    'is_public' => true
                ])->all()
            ))->create();

        $dontHideBookmarkIDs->tap(fn (Collection $collection) => FolderBookmark::insert(
            $collection->map(fn (int $bookmarkID) => [
                'folder_id' => $folder->id,
                'bookmark_id' => $bookmarkID,
                'is_public' => true
            ])->all()
        ));

        $this->hideFolderResponse([
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

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => collect()->times(30)->implode(',')
        ])->assertForbidden();
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->hideFolderResponse([
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

        $folder = FolderFactory::new()->for($user)->create();

        $this->hideFolderResponse([
            'folder_id' => $folder->id,
            'bookmarks' => $bookmarkIDs->map(fn (int $bookmarkID) => $bookmarkID + 1)->implode(',')
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists in folder"
            ]);
    }
}
