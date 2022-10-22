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
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class AddBookmarksToFolderTest extends TestCase
{
    use WithFaker, WillCheckBookmarksHealth;

    protected function addBookmarksToFolderResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('addBookmarksToFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/bookmarks', 'addBookmarksToFolder');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->addBookmarksToFolderResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks', 'folder']);
    }

    public function testBookmark_ids_mustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse(['bookmarks' => '1,2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "bookmarks.1" => ["The bookmarks.1 attribute is invalid"]
            ]);
    }

    public function test_makeHidden_inputValues_mustExists_in_bookmarksInput_values(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse([
            'folder' => 12,
            'bookmarks' => '1,2,3,4,5',
            'make_hidden' => '1,2,3,4,5,6'
        ])->assertJsonValidationErrors([
            'make_hidden.5' => [
                'BookmarkId 6 does not exist in bookmarks.'
            ]
        ]);
    }

    public function testBookmark_ids_mustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function test_makeHidden_inputValues_mustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse([
            'bookmarks' => '1,2,3,4,5',
            'make_hidden' => '1,1,2,3,4,5'
        ])->assertJsonValidationErrors([
            "make_hidden.0" => ["The make_hidden.0 field has a duplicate value."],
            "make_hidden.1" => ["The make_hidden.1 field has a duplicate value."]
        ]);
    }

    public function testCannotAddMoreThan_50_bookmarks_simultaneously(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse(['bookmarks' => implode(',', range(1, 51))])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => 'The bookmarks must not have more than 50 items.'
            ]);
    }

    public function testWillAddBookmarksToFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ])->pluck('id');

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id,
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->id;

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID,
        ])->assertCreated();

        $bookmarkIDs->each(function (int $bookmarkID) use ($folderID) {
            $this->assertDatabaseHas(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID,
                'is_public' => true
            ]);
        });

        $this->assertDatabaseHas(FolderBookmarksCount::class, [
            'folder_id' => $folderID,
            'count' => 10,
        ]);

        //Assert the folder updated_at column was updated
        $this->assertTrue(
            Folder::query()->whereKey($folderID)->first('updated_at')->updated_at->isToday()
        );
    }

    public function testUserWithPermissionCanAddBookmarksToFolder(): void
    {
        [$folderOwner, $user] = UserFactory::new()->count(2)->create();

        Passport::actingAs($user);

        $bookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $user->id]);
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()->user($user->id)->folder($folder->id)->addBookmarksPermission()->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertCreated();
    }

    public function testCollaboratorMustHaveAddBookmarksPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);
        $folderAccessFactory = FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id);
        $collaboratorBookmarks = BookmarkFactory::new()->count(3)->create(['user_id' => $collaborator->id]);

        $folderAccessFactory->updateFolderPermission()->create();
        $folderAccessFactory->removeBookmarksPermission()->create();
        $folderAccessFactory->viewBookmarksPermission()->create();
        $folderAccessFactory->inviteUser()->create();

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $collaboratorBookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testFolderCannotHaveMoreThan_200_Bookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->create($attributes = ['user_id' => $user->id])->id;

        for ($i = 0; $i < 4; $i++) {
            $bookmarkIDs = BookmarkFactory::new()->count(50)->create($attributes)->pluck('id');

            $this->addBookmarksToFolderResponse([
                'bookmarks' => $bookmarkIDs->implode(','),
                'folder' => $folderID,
            ])->assertCreated();
        }

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) BookmarkFactory::new()->create($attributes)->id,
            'folder' => $folderID,
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'folder cannot contain more bookmarks']);
    }

    public function testErrorMessageWhenFolderCannotTakeMoreBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->create($attributes = ['user_id' => $user->id])->id;

        for ($i = 0; $i < 4; $i++) {
            $bookmarkIDs = BookmarkFactory::new()->count(45)->create($attributes)->pluck('id');

            $this->addBookmarksToFolderResponse([
                'bookmarks' => $bookmarkIDs->implode(','),
                'folder' => $folderID,
            ])->assertCreated();
        }

        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->count(21)->create($attributes)->pluck('id')->implode(','),
            'folder' => $folderID,
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'folder can only take only 20 more bookmarks']);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->create(['user_id' => $user->id])->pluck('id');

        $folderID = FolderFactory::new()->create(['user_id' => $user->id])->id;

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder' => $folderID,
        ])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarkIDs->all());
    }

    public function testWillMakeBookmarksHidden(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDsToMakePrivate = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $bookmarkIDsToMakePublic = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id])->pluck('id');
        $folderID = FolderFactory::new()->create(['user_id' => $user->id,])->id;

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIDsToMakePrivate->merge($bookmarkIDsToMakePublic)->shuffle()->implode(','),
            'folder' => $folderID,
            'make_hidden' => $bookmarkIDsToMakePrivate->implode(',')
        ])->assertCreated();

        $bookmarkIDsToMakePublic->each(function (int $bookmarkID) use ($folderID) {
            $this->assertDatabaseHas(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID,
                'is_public' => true
            ]);
        });

        $bookmarkIDsToMakePrivate->each(function (int $bookmarkID) use ($folderID) {
            $this->assertDatabaseHas(FolderBookmark::class, [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID,
                'is_public' => false
            ]);
        });
    }

    public function testCannotAddBookmarkToFolderMoreThanOnce(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertStatus(Response::HTTP_CONFLICT);
    }

    public function testFolderMustBelongTOUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        $otherUsersFolder = FolderFactory::new()->create([
            'user_id' => UserFactory::new()->create()->id //Another user's folder
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $otherUsersFolder->id
        ])->assertForbidden();
    }

    public function testBookmarksMustBelongToUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $otherUsersBookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => UserFactory::new()->create()->id //Another user's bookmarks
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $otherUsersBookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();
    }

    public function testCannotAddInvalidBookmarksToFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $invalidBookmarkIDs = BookmarkFactory::new()
            ->count(3)
            ->create(['user_id' => $user->id])
            ->pluck('id')
            ->map(fn (int $bookmarkID) => $bookmarkID + 1)
            ->implode(',');

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $invalidBookmarkIDs,
            'folder' => $folder->id
        ])
            ->assertNotFound()
            ->assertExactJson([
                'message' => "The bookmarks does not exists"
            ]);
    }

    public function testCannotAddBookmarksToInvalidFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->create([
            'user_id' => $user->id
        ]);

        $folder = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson([
                'message' => "The folder does not exists"
            ]);
    }

    public function testWillNotReturnStaleData(): void
    {
        cache()->setDefaultDriver('redis');

        Passport::actingAs($user = UserFactory::new()->create());

        $folderID = FolderFactory::new()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ])->id;

        //should cache folder.
        $this->getJson(route('fetchFolder', ['id' => $folderID]))
            ->assertOk()
            ->assertJsonFragment([
                'items_count' => 0
            ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->count(3)->create(['user_id' => $user->id])->pluck('id')->implode(','),
            'folder' => $folderID,
        ])->assertCreated();

        $this->getJson(route('fetchFolder', ['id' => $folderID]))
            ->assertOk()
            ->assertJsonFragment([
                'items_count' => 3
            ]);
    }

    public function test_user_with_permission_cannot_add_bookmarks_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->count(3)->create(['user_id' => $collaborator->id])->pluck('id')->implode(','),
            'folder' => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => "The folder does not exists"]);
    }
}
