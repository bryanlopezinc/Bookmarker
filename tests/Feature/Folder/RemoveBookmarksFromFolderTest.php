<?php

namespace Tests\Feature\Folder;

use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RemoveBookmarksFromFolderTest extends TestCase
{
    use WithFaker;

    protected function removeFolderBookmarksResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('removeBookmarksFromFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/bookmarks', 'removeBookmarksFromFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->removeFolderBookmarksResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks', 'folder']);
    }

    public function testWillThrowValidationWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['bookmarks' => '1,2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "folder" => ["The folder field is required."],
                "bookmarks.1" => ["The bookmarks.1 attribute is invalid"]
            ]);
    }

    public function testAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testCannotRemoveMoreThan_50_bookmarks_simultaneously(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['bookmarks' => implode(',', range(1, 51))])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => 'The bookmarks must not have more than 50 items.'
            ]);
    }

    public function testWillRemoveBookmarksFromFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->pluck('id');

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id
        ])->id;

        $this->addBookmarksToFolder($bookmarkIDs->implode(','), $folderID);

        $bookmarksToRemove = $bookmarkIDs->take(9);

        //Remove first 9 bookmarks from folder.
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksToRemove->implode(','),
            'folder' => $folderID
        ])->assertSuccessful();

        $bookmarksToRemove->each(function (int $bookmarkID) use ($folderID) {
            $this->assertDatabaseMissing(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID
            ]);
        });

        //Ensure last bookmark still exists in folder
        $this->assertDatabaseHas(FolderBookmark::class, [
            'bookmark_id' => $bookmarkIDs->last(),
            'folder_id' => $folderID
        ]);

        $this->assertDatabaseHas(FolderBookmarksCount::class, [
            'folder_id' => $folderID,
            'count' => 1,
        ]);

        //Assert the folder updated_at column was updated
        $this->assertTrue(
            Folder::query()->whereKey($folderID)->first('updated_at')->updated_at->isToday()
        );
    }

    public function testWillReturnNotFoundResponseWhenBookmarksDontExistsInFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ])->pluck('id');

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id
        ])->id;

        //Assert will return not found when all bookmarks don't exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists in folder"
            ]);

        //add some bookmarks to folder.
        $this->addBookmarksToFolder($bookmarkIDs->take(1)->implode(','), $folderID);

        //Assert will return not found when some (but not all) bookmarks exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "Bookmarks does not exists in folder"
            ]);
    }

    public function testUserCanOnlyRemoveBookmarksFromOwnFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => UserFactory::new()->create()->id //Another user's folder
        ]);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testUserWithPermissionCanRemoveBookmarksFromFolder(): void
    {
        [$folderOwner, $user] = UserFactory::new()->count(2)->create();

        $bookmarkIDs = BookmarkFactory::times(3)->create(['user_id' => $folderOwner->id])->pluck('id');
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        Passport::actingAs($folderOwner);

        $this->addBookmarksToFolder($bookmarkIDs->implode(','), $folderID);

        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folderID)
            ->removeBookmarksPermission()
            ->create();

        Passport::actingAs($user);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertOk();
    }

    public function testUserWithOnlyViewPermissionCannotRemoveBookmarks(): void
    {
        [$folderOwner, $user] = UserFactory::new()->count(2)->create();

        $bookmarkIDs = BookmarkFactory::times(3)->create(['user_id' => $folderOwner->id])->pluck('id');
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        Passport::actingAs($folderOwner);

        $this->addBookmarksToFolder($bookmarkIDs->implode(','), $folderID);

        FolderAccessFactory::new()
            ->user($user->id)
            ->folder($folderID)
            ->viewBookmarksPermission()
            ->create();

        Passport::actingAs($user);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertForbidden();
    }

    public function testCollaboratorMustHaveRemoveBookmarksPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->create(['user_id' => $folderOwner->id])->pluck('id');
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;
        $folderAccessFactory = FolderAccessFactory::new()->user($collaborator->id)->folder($folderID);

        $folderAccessFactory->updateFolderPermission()->create();
        $folderAccessFactory->addBookmarksPermission()->create();
        $folderAccessFactory->viewBookmarksPermission()->create();
        $folderAccessFactory->inviteUser()->create();

        Passport::actingAs($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->implode(','), $folderID);

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertForbidden();
    }

    private function addBookmarksToFolder(string $bookmarkIDs, int $folderID): void
    {
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarkIDs,
            'folder' => $folderID
        ])->assertCreated();
    }

    public function testUserCannotRemoveBookmarksFromInvalidFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "The folder does not exists"
            ]);
    }

    public function testUserCannotRemoveBookmarksFromFolderMoreThanOnce(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->pluck('id');

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;

        //add bookmarks to folder.
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertCreated();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertSuccessful();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertNotFound();
    }

    public function testWillNotReturnStaleData(): void
    {
        cache()->setDefaultDriver('redis');

        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->pluck('id');

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;

        //add bookmarks to folder.
        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID
        ])->assertCreated();

        //should cache folder.
        $this->getJson(route('fetchFolder', ['id' => $folderID]))
            ->assertOk()
            ->assertJsonFragment([
                'items_count' => 10
            ]);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->take(9)->implode(','),
            'folder' => $folderID
        ])->assertSuccessful();

        $this->getJson(route('fetchFolder', ['id' => $folderID]))
            ->assertOk()
            ->assertJsonFragment([
                'items_count' => 1
            ]);
    }

    public function test_user_with_permission_cannot_remove_bookmarks_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->removeBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => '1,2,3',
            'folder' => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }
}
