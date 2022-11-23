<?php

namespace Tests\Feature\Folder;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\Models\UserBookmarksCount;
use App\Models\UserFoldersCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;
use Tests\Traits\CreatesBookmark;

class DeleteFolderTest extends TestCase
{
    use WithFaker, CreatesBookmark, WillCheckBookmarksHealth;

    protected function deleteFolderResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'createFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteFolderResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteFolderResponse()->assertJsonValidationErrors(['folder']);
    }

    public function testDeleteFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderIDs = FolderFactory::new()->for($user)->count(3)->create()->pluck('id');
        $folderIDToDelete = $folderIDs->random();

        //Save 3 bookmarks for user
        for ($i = 0; $i < 3; $i++)  $this->saveBookmark();

        $userBookmarks = Bookmark::query()
            ->where('user_id', $user->id)
            ->get()
            // add user bookmarks to a folder.
            ->tap(fn (Collection $bookmarks) => $this->postJson(route('addBookmarksToFolder'), [
                'bookmarks' => $bookmarks->pluck('id')->implode(','),
                'folder' => $folderIDToDelete
            ])->assertCreated());

        UserFoldersCount::create([
            'count' => 3,
            'user_id' => $user->id
        ]);

        $this->deleteFolderResponse(['folder' => $folderIDToDelete])->assertOk();

        //assert no other folder was deleted
        $folderIDs->reject($folderIDToDelete)->each(function (int $folderID) {
            $this->assertDatabaseHas(Folder::class, ['id' => $folderID]);
        });

        $this->assertDatabaseMissing(Folder::class, ['id' => $folderIDToDelete]);
        $this->assertDatabaseMissing(FolderBookmarksCount::class, ['folder_id' => $folderIDToDelete,]);
        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 2,
            'type' => UserFoldersCount::TYPE
        ]);

        $userBookmarks->each(function (Bookmark $bookmark) use ($folderIDToDelete) {
            //Assert records where deleted in folder_bookmarks table
            $this->assertDatabaseMissing(FolderBookmark::class, [
                'bookmark_id' => $bookmark->id,
                'folder_id' => $folderIDToDelete
            ]);

            //Assert bookmark in folder was not deleted
            $this->assertDatabaseHas(Bookmark::class, ['id' => $bookmark->id,]);
        });
    }

    public function testDeleteFolderAndItsItems(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->for($user)->create()->id;

        UserFoldersCount::create([
            'count' => 1,
            'user_id' => $user->id
        ]);

        //Save 3 bookmarks for user
        for ($i = 0; $i < 3; $i++)  $this->saveBookmark();

        $userBookmarks =  Bookmark::query()
            ->where('user_id', $user->id)
            ->get()
            // add user bookmarks to a folder.
            ->tap(fn (Collection $bookmarks) => $this->postJson(route('addBookmarksToFolder'), [
                'bookmarks' => $bookmarks->pluck('id')->implode(','),
                'folder' => $folderID
            ])->assertCreated());

        //delete folder and delete all bookmarks in folder
        $this->deleteFolderResponse([
            'folder' => $folderID,
            'delete_bookmarks' => true
        ])->assertOk();

        $this->assertDatabaseMissing(Folder::class, ['id' => $folderID]);
        $this->assertDatabaseMissing(FolderBookmarksCount::class, ['folder_id' => $folderID]);

        //Assert user bookmarks count was decremented
        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserBookmarksCount::TYPE
        ]);

        //Assert user folders count was decremented
        $this->assertDatabaseHas(UserFoldersCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserFoldersCount::TYPE
        ]);

        $userBookmarks->each(function (Bookmark $bookmark) use ($folderID) {
            //Assert records where deleted in folder_bookmarks table
            $this->assertDatabaseMissing(FolderBookmark::class, [
                'bookmark_id' => $bookmark->id,
                'folder_id' => $folderID
            ]);

            //Assert bookmark in folder was deleted
            $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmark->id,]);
        });

        $this->assertBookmarksHealthWillNotBeChecked($userBookmarks->pluck('id')->all());
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse(['folder' => $folderID])->assertForbidden();
    }

    public function testCannotDeleteInvalidFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folderID = FolderFactory::new()->create()->id;

        $this->deleteFolderResponse(['folder' => $folderID + 1])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "The folder does not exists"
            ]);;
    }

    public function testWillNotReturnStaleData(): void
    {
        cache()->setDefaultDriver('redis');

        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->for($user)->create(['created_at' => now()])->id;

        //should cache folder.
        $this->getJson(route('fetchFolder', ['id' => $folderID]))->assertOk();

        $this->deleteFolderResponse(['folder' => $folderID])->assertOk();
        $this->getJson(route('fetchFolder', ['id' => $folderID]))->assertNotFound();
        $this->deleteFolderResponse(['folder' => $folderID + 1])->assertNotFound();
    }

    public function testWillNotAffectOtherUserBookmarksWhenFolderAndItsItemsAreDeleted(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        [$userBookmark, $userBookmarkAddedToFolder] = BookmarkFactory::new()->count(2)->for($user)->create();
        $folderID = FolderFactory::new()->for($user)->create()->id;

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => (string) $userBookmarkAddedToFolder->id,
            'folder' => $folderID
        ])->assertCreated();

        $this->deleteFolderResponse([
            'folder' => $folderID,
            'delete_bookmarks' => true
        ])->assertOk();

        $this->assertDatabaseMissing(Bookmark::class, ['id' => $userBookmarkAddedToFolder->id]);
        $this->assertDatabaseHas(Bookmark::class, ['id' => $userBookmark->id]);
    }

    public function test_will_not_delete_bookmarks_added_by_collaborators_when_deleting_folder_and_its_bookmarks(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::new()->count(2)->for($collaborator)->create();
        $folderID = FolderFactory::new()->for($folderOwner)->create(['created_at' => now()])->id;

        FolderAccessFactory::new()
            ->folder($folderID)
            ->user($collaborator->id)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($collaborator);
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $collaboratorBookmarks->pluck('id')->implode(','),
            'folder' => $folderID
        ])->assertCreated();

        Passport::actingAs($folderOwner);
        $this->deleteFolderResponse([
            'folder' => $folderID,
            'delete_bookmarks' => true
        ])->assertOk();

        $this->assertEquals(
            Bookmark::query()->where('user_id', $collaborator->id)->pluck('id')->sort()->all(),
            $collaboratorBookmarks->pluck('id')->sort()->all()
        );
    }
}
