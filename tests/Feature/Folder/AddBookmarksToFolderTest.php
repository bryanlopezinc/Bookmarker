<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\Collections\BookmarkPublicIdsCollection as PublicIds;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\SuspendCollaborator\SuspendCollaborator;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\TestCase;
use Tests\Traits;

class AddBookmarksToFolderTest extends TestCase
{
    use WithFaker;
    use Traits\WillCheckBookmarksHealth;
    use Traits\CreatesCollaboration;
    use Traits\CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use Traits\GeneratesId;

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
        $this->loginUser(UserFactory::new()->create());

        $this->addBookmarksToFolderResponse(['folder' => 'foo', 'bookmarks' => $this->generateBookmarkId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $bookmarkIds = Collection::times(6, fn () => $this->generateBookmarkId()->present());

        $this->addBookmarksToFolderResponse(['folder' => $id = $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks']);

        //Assert each bookmark id must be valid
        $this->addBookmarksToFolderResponse(['bookmarks' => "{$bookmarkIds[0]},2bar", 'folder' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["bookmarks.1" => ["The bookmarks.1 attribute is invalid"]]);

        //Assert bookmark ids in the "make_hidden" attribute must be present in the
        //"bookmarks" attribute
        $this->addBookmarksToFolderResponse([
            'folder'      => $id,
            'bookmarks'   => $bookmarkIds->take(3)->implode(','),
            'make_hidden' => $hidden = $bookmarkIds->last(),
        ])->assertJsonValidationErrors(['make_hidden.0' => ["BookmarkId {$hidden} does not exist in bookmarks."]]);

        //Assert bookmarks ids must be unique
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->take(3)->add($bookmarkIds[0])->implode(','),
            'folder'    => $id
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.3" => ["The bookmarks.3 field has a duplicate value."]
        ]);

        //Assert make hidden ids must be unique
        $this->addBookmarksToFolderResponse([
            'bookmarks'   => $bookmarkIds->take(3)->implode(','),
            'make_hidden' => $bookmarkIds->take(3)->add($bookmarkIds[0])->implode(','),
            'folder'      => $id
        ])->assertJsonValidationErrors([
            "make_hidden.0" => ["The make_hidden.0 field has a duplicate value."],
            "make_hidden.3" => ["The make_hidden.3 field has a duplicate value."]
        ]);

        // assert bookmarks cannot be greater than 50
        $this->addBookmarksToFolderResponse([
            'bookmarks' => Collection::times(51, fn () => $this->generateBookmarkId()->present())->implode(','),
            'folder' => $id
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks' => 'The bookmarks must not have more than 50 items.']);
    }

    public function testWhenFolderOwnerAddsBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create([
            'created_at' => $createdAt = now()->yesterday(),
            'updated_at' => $createdAt,
        ]);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertCreated();

        $this->assertEquals($folder->bookmarks->sole()->id, $bookmark->id);

        //Assert the folder updated_at column was updated
        $this->assertTrue($folder->refresh()->updated_at->isToday());
        $this->assertNoMetricsRecorded($user->id, $folder->id, CollaboratorMetricType::BOOKMARKS_ADDED);
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
            'bookmarks' => $bookmark->public_id->present(),
            'folder'    => $folder->public_id->present()
        ])->assertCreated();
    }

    #[Test]
    public function collaboratorWithPermissionCanAddBookmarks(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::times(4)->for($collaborator)->create())->present();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($collaborator);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->take(2)->implode(','),
            'folder'    => $folder->public_id->present()
        ])->assertCreated();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::BOOKMARKS_ADDED, 2);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->slice(-2)->implode(','),
            'folder'    => $folder->public_id->present()
        ])->assertCreated();

        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 4);
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
            'bookmarks' => $collaboratorBookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsSuspended(): void
    {
        [$folderOwner, $suspendedCollaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($suspendedCollaborator)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder, Permission::ADD_BOOKMARKS);
        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($suspendedCollaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $collaboratorBookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'CollaboratorSuspended']);
    }

    #[Test]
    public function suspendedCollaboratorCanAddBookmarksToFolderWhenSuspensionDurationIsPast(): void
    {
        $this->loginUser($suspendedCollaborator = UserFactory::new()->create());

        $folder = FolderFactory::new()->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($suspendedCollaborator)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder, Permission::ADD_BOOKMARKS);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder, suspensionDurationInHours: 1);

        $this->travel(57)->minutes(function () use ($folder, $collaboratorBookmark) {
            $this->addBookmarksToFolderResponse([
                'bookmarks' => $collaboratorBookmark->public_id->present(),
                'folder'    => $folder->public_id->present(),
            ])->assertForbidden()->assertJsonFragment(['message' => 'CollaboratorSuspended']);
        });

        $this->travel(62)->minutes(function () use ($folder, $collaboratorBookmark) {
            $this->addBookmarksToFolderResponse([
                'bookmarks' => $collaboratorBookmark->public_id->present(),
                'folder'    => $folder->public_id->present(),
            ])->assertCreated();
        });

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
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
            'bookmarks' => $collaboratorBookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
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
            'bookmarks' => BookmarkFactory::new()->for($folderOwner)->create()->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->for($collaborator)->create()->public_id->present(),
            'folder'    => $folder->public_id->present(),
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
            'bookmarks' => BookmarkFactory::new()->for($folderOwner)->create()->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => BookmarkFactory::new()->for($collaborator)->create()->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'FolderBookmarksLimitReached']);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->for($user)->create();
        $folderID = FolderFactory::new()->for($user)->create()->public_id->present();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => PublicIds::fromObjects($bookmarks)->present()->implode(','),
            'folder'    => $folderID,
        ])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillMakeBookmarksHidden(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkToMakePrivate = BookmarkFactory::new()->for($user)->create();
        $bookmarkToMakePublic = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks'   => implode(',', [$bookmarkToMakePrivate->public_id->present(), $bookmarkToMakePublic->public_id->present()]),
            'folder'      => $folder->public_id->present(),
            'make_hidden' => $bookmarkToMakePrivate->public_id->present(),
        ])->assertCreated();

        $folderBookmarks = FolderBookmark::where('folder_id', $folder->id)->get();

        $this->assertEquals($folderBookmarks->where('bookmark_id', $bookmarkToMakePublic->id)->first()->visibility, 'public');
        $this->assertEquals($folderBookmarks->where('bookmark_id', $bookmarkToMakePrivate->id)->first()->visibility, 'private');
    }

    public function testWillReturnBadRequestWhenCollaboratorMarksBookmarksAsHidden(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::new()->count(3)->for($collaborator)->create())->present();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks'   => $bookmarkIds->implode(','),
            'folder'      => $folder->public_id->present(),
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
            'bookmarks' => $bookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertCreated();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertStatus(Response::HTTP_CONFLICT)->assertJsonFragment(['message' => 'FolderContainsBookmarks']);

        $this->assertCount(1, $folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongTOUserAndUserDoesNotHavePermission(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::new()->count(2)->for($user)->create())->present();

        $otherUsersFolder = FolderFactory::new()->for(UserFactory::new()->create())->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->implode(','),
            'folder'    => $otherUsersFolder->public_id->present(),
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEmpty($otherUsersFolder->bookmarks);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotBelongToUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $otherUsersBookmarkIds = PublicIds::fromObjects(BookmarkFactory::new()->count(2)->create([]))->present();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $otherUsersBookmarkIds->implode(','),
            'folder'    => $folder->public_id->present(),
        ])->assertNotFound()->assertJsonFragment(['message' => 'BookmarkNotFound']);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => implode(',', [$bookmark->public_id->present(), $this->generateBookmarkId()->present()]),
            'folder'    => $folder->public_id->present(),
        ])->assertNotFound()->assertJsonFragment(['message' => "BookmarkNotFound"]);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::new()->count(3)->for($user)->create())->present();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->implode(','),
            'folder'    => $this->generateFolderId()->present(),
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
            'bookmarks' => PublicIds::fromObjects(BookmarkFactory::new()->count(3)->for($collaborator)->create())->present()->implode(','),
            'folder'    => $folder->public_id->present(),
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);

        $this->assertEmpty($folder->bookmarks);
    }

    public function testWillNotSendNotificationWhenBookmarksWereAddedByFolderOwner(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $folderID = FolderFactory::new()->for($user)->create()->public_id->present();

        Notification::fake();

        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmark->public_id->present(),
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
            'bookmarks' => $bookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertCreated();

        $notificationData = $folderOwner->notifications()->first(['data', 'type']);

        $this->assertEquals('BookmarksAddedToFolder', $notificationData->type);
        $this->assertEquals($notificationData->data, [
            'N-type' => 'BookmarksAddedToFolder',
            'version' => '1.0.0',
            'bookmark_ids' => [$bookmark->id],
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value
            ],
            'collaborator' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value
            ],
        ]);
    }

    public function testWillNotSendNotificationWhenNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::new()->count(3)->for($collaborator)->create())->present();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->implode(','),
            'folder' => $folder->public_id->present(),
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationWhenNewBookmarksNotificationIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::new()->count(3)->for($collaborator)->create())->present();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNewBookmarksNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $bookmarkIds->implode(','),
            'folder' => $folder->public_id->present(),
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotNotifyFolderOwnerWhenCollaboratorIsMuted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIs = PublicIds::fromObjects(BookmarkFactory::times(2)->for($collaborator)->create())->present();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse(['bookmarks' => $bookmarkIs->implode(','), 'folder' => $folder->public_id->present()])->assertCreated();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotifyFolderOwnerWhenMuteDurationIsPast(): void
    {
        Notification::fake();

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIds = PublicIds::fromObjects(BookmarkFactory::times(2)->for($collaborator)->create())->present();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id, now(), 1);

        $this->loginUser($collaborator);
        $this->travel(61)->minutes(function () use ($bookmarkIds, $folder) {
            $this->addBookmarksToFolderResponse(['bookmarks' => $bookmarkIds->implode(','), 'folder' => $folder->public_id->present()])->assertCreated();

            Notification::assertCount(1);
        });
    }

    #[Test]
    public function willReturnCorrectResponseWhenFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $collaboratorBookmarkIds = PublicIds::fromObjects(BookmarkFactory::times(2)->for($collaborator)->create())->present();
        $folderOwnerBookmark = BookmarkFactory::new()->for($folderOwner)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        //Assert collaborator can add bookmark when disabled action is not addBookmarks action
        $updateCollaboratorActionService->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => $collaboratorBookmarkIds->implode(','),
            'folder'    => $folder->public_id->present(),
        ])->assertCreated();

        $updateCollaboratorActionService->disable($folder->id, Feature::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolderResponse($query = [
            'bookmarks' => $folderOwnerBookmark->public_id->present(),
            'folder'    => $folder->public_id->present(),
        ])->assertCreated();

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->addBookmarksToFolderResponse($query)
            ->assertNotFound();

        $this->loginUser($collaborator);
        $this->addBookmarksToFolderResponse([
            'bookmarks' => (string) $collaboratorBookmarkIds[1],
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);
    }
}
