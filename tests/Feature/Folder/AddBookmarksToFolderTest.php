<?php

namespace Tests\Feature\Folder;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Services\Folder\AddBookmarksToFolderService;
use App\Services\Folder\MuteCollaboratorService;
use App\Services\Folder\ToggleFolderCollaborationRestriction;
use App\UAC;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\WillCheckBookmarksHealth;

class AddBookmarksToFolderTest extends TestCase
{
    use WithFaker,
        WillCheckBookmarksHealth,
        CreatesCollaboration;

    protected function addBookmarksToFolderResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('addBookmarksToFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/bookmarks', 'addBookmarksToFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->addBookmarksToFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks', 'folder']);

        //Assert each bookmark id must be valid
        $this->addBookmarksToFolderResponse(['bookmarks' => '1,2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["bookmarks.1" => ["The bookmarks.1 attribute is invalid"]]);

        //Assert bookmark ids in the "make_hidden" parameter must be present in the
        //"bookmarks" parameter
        $this->addBookmarksToFolderResponse([
            'folder'      => 12,
            'bookmarks'   => '1,2,3,4,5',
            'make_hidden' => '1,2,3,4,5,6'
        ])->assertJsonValidationErrors([
            'make_hidden.5' => ['BookmarkId 6 does not exist in bookmarks.']
        ]);

        //Assert bookmarks ids must be unique
        $this->addBookmarksToFolderResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);

        //Assert make hidden ids must be unique
        $this->addBookmarksToFolderResponse([
            'bookmarks'   => '1,2',
            'make_hidden' => '1,1,2'
        ])->assertJsonValidationErrors([
            "make_hidden.0" => ["The make_hidden.0 field has a duplicate value."],
            "make_hidden.1" => ["The make_hidden.1 field has a duplicate value."]
        ]);

        // assert bookmarks cannot be greater than 50
        $this->addBookmarksToFolderResponse(['bookmarks' => implode(',', range(1, 51))])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks' => 'The bookmarks must not have more than 50 items.']);
    }

    public function testAddBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folderId = FolderFactory::new()->for($user)->create([
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ])->id;

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folderId,
        ])->assertCreated();

        $this->assertDatabaseHas(FolderBookmark::class, [
            'folder_id'   => $folderId,
            'bookmark_id' => $bookmark->id,
        ]);

        //Assert the folder updated_at column was updated
        $this->assertTrue(
            Folder::query()->whereKey($folderId)->first('updated_at')->updated_at->isToday()
        );
    }

    public function testWillNotSendNotificationWhenBookmarksWereAddedByFolderOwner(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folderID = FolderFactory::new()->for($user)->create()->id;

        Notification::fake();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folderID,
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillSendNotificationsWhenBookmarksWereNotAddedByFolderOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmark = BookmarkFactory::new()->for($collaborator)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id
        ])->assertCreated();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folderOwner->id)->first(['data'])->data;

        $this->assertEquals($folder->id, $notificationData['added_to_folder']);
        $this->assertEquals($collaborator->id, $notificationData['added_by']);
        $this->assertEquals([$bookmark->id], $notificationData['bookmarks_added_to_folder']);
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveAddBookmarksPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::ADD_BOOKMARKS->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmark->id,
            'folder'    => $folder->id
        ])->assertForbidden()
            ->assertExactJson(['message' => 'NoAddBookmarkPermission']);
    }

    public function testWillReturnForbiddenWhenFolderHas_200_Bookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        Folder::retrieved(function (Folder $retrieved) use ($folder) {
            if ($retrieved->id !== $folder->id) {
                return;
            }

            $retrieved->bookmarksCount = 200;
        });

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) BookmarkFactory::new()->for($user)->create()->id,
            'folder'    => $folder->id,
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'folderBookmarksLimitReached']);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(10)->for($user)->create()->pluck('id');
        $folderID = FolderFactory::new()->for($user)->create()->id;

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIDs->implode(','),
            'folder'    => $folderID,
        ])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarkIDs->all());
    }

    public function testWillMakeBookmarksHidden(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarkToMakePrivate = BookmarkFactory::new()->for($user)->create();
        $bookmarkToMakePublic = BookmarkFactory::new()->for($user)->create();

        $folderID = FolderFactory::new()->for($user)->create()->id;

        $this->addBookmarksToFolderResponse([
            'bookmarks'   => implode(',', [$bookmarkToMakePrivate->id, $bookmarkToMakePublic->id]),
            'folder'      => $folderID,
            'make_hidden' => (string) $bookmarkToMakePrivate->id
        ])->assertCreated();

        $folderBookmarks = FolderBookmark::where('folder_id', $folderID)->get();

        $this->assertEquals($folderBookmarks->where('bookmark_id', $bookmarkToMakePublic->id)->first()->visibility, 'public');
        $this->assertEquals($folderBookmarks->where('bookmark_id', $bookmarkToMakePrivate->id)->first()->visibility, 'private');
    }

    public function testWillReturnBadRequestWhenCollaboratorMarksBookmarksAsHidden(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $bookmarkIds = BookmarkFactory::new()->count(3)->for($collaborator)->create()->pluck('id');

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks'   => $bookmarkIds->implode(','),
            'folder'      => $folder->id,
            'make_hidden' => (string) $bookmarkIds->first()
        ])->assertStatus(400)
            ->assertExactJson(['message' => 'collaboratorCannotMakeBookmarksHidden']);
    }

    public function testWillReturnConflictWhenFolderContainsBookmark(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();
        $folder = FolderFactory::new()->for($user)->create();

        /** @var AddBookmarksToFolderService */
        $service = app(AddBookmarksToFolderService::class);

        $service->add($folder->id, $bookmark->id);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => 'FolderContainsBookmarks']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongTOUserAndUserDoesNotHavePermission(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $otherUsersFolder = FolderFactory::new()->for(UserFactory::new()->create())->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder'    => $otherUsersFolder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotBelongToUser(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $otherUsersBookmarks = BookmarkFactory::new()->count(2)->create([]);

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $otherUsersBookmarks->pluck('id')->implode(','),
            'folder'    => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => implode(',', [$bookmark->id, $bookmark->id + 1]),
            'folder'    => $folder->id
        ])
            ->assertNotFound()
            ->assertExactJson(['message' => "BookmarkNotFound"]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder'    => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function testWillReturnNotFoundWhenUserWithPermissionIsAddingBookmarksToADeletedUserFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $folderOwner->delete();

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->count(3)->for($collaborator)->create()->pluck('id')->implode(','),
            'folder'    => $folder->id,
        ])->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function testWillNotSendNotificationWhenNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarks = BookmarkFactory::new()->count(3)->for($collaborator)->create()->pluck('id');

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationWhenNewBookmarksNotificationIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarks = BookmarkFactory::new()->count(3)->for($collaborator)->create()->pluck('id');

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNewBookmarksNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->implode(','),
            'folder' => $folder->id
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotNotifyFolderOwnerWhenCollaboratorIsMuted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarks = BookmarkFactory::times(2)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse(['bookmarks' => $bookmarks->implode(','), 'folder' => $folder->id])->assertCreated();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willReturnCorrectResponseWhenActionsIsDisabled(): void
    {
        /** @var ToggleFolderCollaborationRestriction */
        $updateCollaboratorActionService = app(ToggleFolderCollaborationRestriction::class);

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $collaboratorBookmarks = BookmarkFactory::times(2)->for($collaborator)->create();
        $folderOwnerBookmark = BookmarkFactory::new()->for($folderOwner)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        //Assert collaborator can add bookmark when disabled action is not addBookmarks action
        $updateCollaboratorActionService->update($folder->id, Permission::INVITE_USER, false);
        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmarks[0]->id,
            'folder'    => $folder->id
        ])->assertCreated();

        $updateCollaboratorActionService->update($folder->id, Permission::ADD_BOOKMARKS, false);

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolderResponse($query = [
            'bookmarks' => (string) $folderOwnerBookmark->id,
            'folder'    => $folder->id
        ])->assertCreated();

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->addBookmarksToFolderResponse($query)->assertNotFound();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmarks[1]->id,
            'folder'    => $folder->id
        ])->assertForbidden()
            ->assertExactJson(['message' => 'AddBookmarksActionDisabled']);
    }
}
