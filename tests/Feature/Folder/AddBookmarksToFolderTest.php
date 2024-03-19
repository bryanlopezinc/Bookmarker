<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Services\Folder\MuteCollaboratorService;
use App\UAC;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\WillCheckBookmarksHealth;

class AddBookmarksToFolderTest extends TestCase
{
    use WithFaker;
    use WillCheckBookmarksHealth;
    use CreatesCollaboration;
    use CreatesRole;

    protected function addBookmarksToFolderResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(
            route('addBookmarksToFolder', ['folder_id' => $parameters['folder']]),
            Arr::except($parameters, ['folder'])
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks', 'addBookmarksToFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->addBookmarksToFolderResponse(['folder' => 5])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->addBookmarksToFolderResponse(['folder' => 'foo'])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse(['folder' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks']);

        //Assert each bookmark id must be valid
        $this->addBookmarksToFolderResponse(['bookmarks' => '1,2bar', 'folder' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["bookmarks.1" => ["The bookmarks.1 attribute is invalid"]]);

        //Assert bookmark ids in the "make_hidden" parameter must be present in the
        //"bookmarks" parameter
        $this->addBookmarksToFolderResponse([
            'folder'      => 12,
            'bookmarks'   => '1,2,3,4,5',
            'make_hidden' => '1,2,3,4,5,6',
        ])->assertJsonValidationErrors(['make_hidden.5' => ['BookmarkId 6 does not exist in bookmarks.']]);

        //Assert bookmarks ids must be unique
        $this->addBookmarksToFolderResponse([
            'bookmarks' => '1,1,3,4,5',
            'folder'    => 4
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);

        //Assert make hidden ids must be unique
        $this->addBookmarksToFolderResponse([
            'bookmarks'   => '1,2',
            'make_hidden' => '1,1,2',
            'folder'      => 4
        ])->assertJsonValidationErrors([
            "make_hidden.0" => ["The make_hidden.0 field has a duplicate value."],
            "make_hidden.1" => ["The make_hidden.1 field has a duplicate value."]
        ]);

        // assert bookmarks cannot be greater than 50
        $this->addBookmarksToFolderResponse(['bookmarks' => implode(',', range(1, 51)), 'folder' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks' => 'The bookmarks must not have more than 50 items.']);
    }

    public function testAddBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create([
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id,
        ])->assertCreated();

        $this->assertEquals($folder->bookmarks->sole()->id, $bookmark->id);

        //Assert the folder updated_at column was updated
        $this->assertTrue($folder->refresh()->updated_at->isToday());
    }

    #[Test]
    public function collaboratorWithRoleCanAddBookmarks(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->attachRoleToUser(UserFactory::new()->create(), $role = $this->createRole('creator', $folder, Permission::ADD_BOOKMARKS));
        $this->attachRoleToUser($collaborator, $role);
        $this->attachRoleToUser($collaborator, $this->createRole(permissions: Permission::ADD_BOOKMARKS));

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id,
        ])->assertCreated();
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveAddBookmarksPermissionOrRole(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::ADD_BOOKMARKS->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        $this->attachRoleToUser($collaborator, $this->createRole('Bar', $folder, Permission::INVITE_USER));
        $this->attachRoleToUser($collaborator, $this->createRole('Foo', FolderFactory::new()->create(), Permission::ADD_BOOKMARKS));

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmark->id,
            'folder'    => $folder->id,
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorRoleNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->attachRoleToUser($collaborator, $role = $this->createRole('creator', $folder, Permission::ADD_BOOKMARKS));

        $role->delete();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmark->id,
            'folder'    => $folder->id,
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);
    }

    public function testWillReturnForbiddenWhenFolderHas_200_Bookmarks(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Folder::retrieved(function (Folder $retrieved) use ($folder) {
            if ($retrieved->id !== $folder->id) {
                return;
            }

            $retrieved->bookmarks_count = 200;
        });

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) BookmarkFactory::new()->for($folderOwner)->create()->id,
            'folder'    => $folder->id,
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) BookmarkFactory::new()->for($collaborator)->create()->id,
            'folder'    => $folder->id,
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);
    }

    #[Test]
    public function willReturnForbiddenWhenFolderOwnerSetLimitIsExceeded(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->setMaxBookmarksLimit(100))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Folder::retrieved(function (Folder $retrieved) {
            $retrieved->bookmarks_count = 100;
        });

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) BookmarkFactory::new()->for($folderOwner)->create()->id,
            'folder'    => $folder->id,
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) BookmarkFactory::new()->for($collaborator)->create()->id,
            'folder'    => $folder->id,
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

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
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkToMakePrivate = BookmarkFactory::new()->for($user)->create();
        $bookmarkToMakePublic = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks'   => implode(',', [$bookmarkToMakePrivate->id, $bookmarkToMakePublic->id]),
            'folder'      => $folder->id,
            'make_hidden' => (string) $bookmarkToMakePrivate->id,
        ])->assertCreated();

        $folderBookmarks = FolderBookmark::where('folder_id', $folder->id)->get();

        $this->assertEquals($folderBookmarks->where('bookmark_id', $bookmarkToMakePublic->id)->first()->visibility, 'public');
        $this->assertEquals($folderBookmarks->where('bookmark_id', $bookmarkToMakePrivate->id)->first()->visibility, 'private');
    }

    public function testWillReturnBadRequestWhenCollaboratorMarksBookmarksAsHidden(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $bookmarkIds = BookmarkFactory::new()->count(3)->for($collaborator)->create()->pluck('id');

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks'   => $bookmarkIds->implode(','),
            'folder'      => $folder->id,
            'make_hidden' => (string) $bookmarkIds->first(),
        ])->assertStatus(400)->assertJsonFragment(['message' => 'CollaboratorCannotMakeBookmarksHidden']);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillReturnConflictWhenFolderContainsBookmark(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id,
        ])->assertCreated();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id,
        ])->assertStatus(Response::HTTP_CONFLICT)->assertJsonFragment(['message' => 'FolderContainsBookmarks']);

        $this->assertCount(1, $folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongTOUserAndUserDoesNotHavePermission(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $otherUsersFolder = FolderFactory::new()->for(UserFactory::new()->create())->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder'    => $otherUsersFolder->id,
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEmpty($otherUsersFolder->bookmarks);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotBelongToUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $otherUsersBookmarks = BookmarkFactory::new()->count(2)->create([]);

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $otherUsersBookmarks->pluck('id')->implode(','),
            'folder'    => $folder->id,
        ])->assertNotFound()->assertJsonFragment(['message' => 'BookmarkNotFound']);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => implode(',', [$bookmark->id, $bookmark->id + 1]),
            'folder'    => $folder->id,
        ])->assertNotFound()->assertJsonFragment(['message' => "BookmarkNotFound"]);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->pluck('id')->implode(','),
            'folder'    => $folder->id + 1,
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function testWillReturnNotFoundWhenUserWithPermissionIsAddingBookmarksToADeletedUserFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->count(3)->for($collaborator)->create()->pluck('id')->implode(','),
            'folder'    => $folder->id,
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillNotSendNotificationWhenBookmarksWereAddedByFolderOwner(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

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

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $bookmark->id,
            'folder'    => $folder->id,
        ])->assertCreated();

        $notificationData = $folderOwner->notifications()->first(['data', 'type']);

        $this->assertEquals('BookmarksAddedToFolder', $notificationData->type);
        $this->assertEquals($notificationData->data, [
            'N-type' => 'BookmarksAddedToFolder',
            'version' => '1.0.0',
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'full_name' => $collaborator->full_name->value,
            'folder_name' => $folder->name->value,
            'bookmark_ids' => [$bookmark->id]
        ]);
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

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->implode(','),
            'folder' => $folder->id,
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

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarks->implode(','),
            'folder' => $folder->id,
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
    public function willNotifyFolderOwnerWhenMuteDurationIsPast(): void
    {
        Notification::fake();

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarks = BookmarkFactory::times(2)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id, now(), 1);

        $this->loginUser($collaborator);
        $this->travel(61)->minutes(function () use ($bookmarks, $folder) {
            $this->addBookmarksToFolderResponse(['bookmarks' => $bookmarks->implode(','), 'folder' => $folder->id])->assertCreated();

            Notification::assertCount(1);
        });
    }

    #[Test]
    public function willReturnCorrectResponseWhenFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $collaboratorBookmarks = BookmarkFactory::times(2)->for($collaborator)->create();
        $folderOwnerBookmark = BookmarkFactory::new()->for($folderOwner)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        //Assert collaborator can add bookmark when disabled action is not addBookmarks action
        $updateCollaboratorActionService->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmarks[0]->id,
            'folder'    => $folder->id,
        ])->assertCreated();

        $updateCollaboratorActionService->disable($folder->id, Feature::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolderResponse($query = [
            'bookmarks' => (string) $folderOwnerBookmark->id,
            'folder'    => $folder->id,
        ])->assertCreated();

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->addBookmarksToFolderResponse($query)
            ->assertNotFound();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmarks[1]->id,
            'folder'    => $folder->id,
        ])->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);
    }
}
