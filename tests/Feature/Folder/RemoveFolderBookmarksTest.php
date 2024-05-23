<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Actions\ToggleFolderFeature;
use App\Collections\BookmarkPublicIdsCollection;
use App\DataTransferObjects\Activities\FolderBookmarksRemovedActivityLogData;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\SuspendCollaborator\SuspendCollaborator;
use App\Models\Bookmark;
use App\Services\Folder\MuteCollaboratorService;
use App\UAC;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;

class RemoveFolderBookmarksTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use GeneratesId;

    protected function removeFolderBookmarksResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists('bookmarks', $parameters)) {
            $parameters['bookmarks'] = implode(',', Arr::wrap($parameters['bookmarks']));
        }

        return $this->deleteJson(
            route('removeBookmarksFromFolder', ['folder_id' => $parameters['folder']]),
            Arr::except($parameters, ['folder'])
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/bookmarks', 'removeBookmarksFromFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->removeFolderBookmarksResponse(['folder' => 4])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['folder' => 'foo', 'bookmarks' => [$this->generateBookmarkId()->present()]])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['folder' => $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $bookmarksPublicIds = $this->generateBookmarkIds(51)->present();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$bookmarksPublicIds[0], '2bar'],
            'folder' => $id = $this->generateFolderId()->present()
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(["bookmarks.1" => ["The bookmarks.1 attribute is invalid"]]);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->take(3)->add($bookmarksPublicIds[0])->all(),
            'folder'    => $id
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.3" => ["The bookmarks.3 field has a duplicate value."]
        ]);

        $this->removeFolderBookmarksResponse(['bookmarks' => $bookmarksPublicIds->all(), 'folder' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks' => 'The bookmarks must not have more than 50 items.']);
    }

    public function testRemoveBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($user)->create(['updated_at' => now()->subDay()]);

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$bookmarksPublicIds[0]],
            'folder'    => $folder->public_id->present()
        ])->assertOk();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertCount(1, $folder->bookmarks);
        $this->assertCount(2, $bookmarks->toQuery()->get());
        $this->assertEquals($folder->bookmarks->first()->id, $bookmarkIDs[1]);
        $this->assertNoMetricsRecorded($user->id, $folder->id, CollaboratorMetricType::BOOKMARKS_DELETED);

        //Assert the folder updated_at column was updated
        $this->assertTrue($folder->refresh()->updated_at->isToday());

        $this->assertEquals($activity->type, ActivityType::BOOKMARKS_REMOVED);
        $this->assertEquals($activity->data, (new FolderBookmarksRemovedActivityLogData(collect([$bookmarks[0]]), $user))->toArray());
    }

    public function testUserWithPermissionCanRemoveBookmarksFromFolder(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(4)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($collaborator);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->take(2)->all(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->assertCount(2, $folder->bookmarks);
        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::BOOKMARKS_DELETED, 2);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->slice(-2)->implode(','),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 4);
    }

    public function testUserWithRoleCanRemoveBookmarksFromFolder(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: [Permission::INVITE_USER, Permission::DELETE_BOOKMARKS]));

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $folder->bookmarks);
    }

    #[Test]
    public function whenBookmarkIsHidden(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::times(2)->for($folderOwner)->create();
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id, [$bookmarks[0]->id]);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse($query = [
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder' => $folder->public_id->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'BookmarkNotFound']);

        $this->assertCount(2, $folder->bookmarks);

        $this->loginUser($folderOwner);
        $this->removeFolderBookmarksResponse($query)->assertOk();
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveRemoveBookmarksPermissionOrRole(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $permissions = UAC::all()->except(Permission::DELETE_BOOKMARKS)->toArray();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->create(), permissions: Permission::DELETE_BOOKMARKS));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->for($folderOwner)->create(), permissions: Permission::DELETE_BOOKMARKS));

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(3, $folder->bookmarks);
    }

    public function willReturnForbiddenWhenCollaboratorRoleNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder, permissions: [Permission::DELETE_BOOKMARKS]));

        $role->delete();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(3, $folder->bookmarks);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsSuspended(): void
    {
        [$folderOwner, $suspendedCollaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $bookmarks = BookmarkFactory::times(3)->create();
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);
        $this->CreateCollaborationRecord($suspendedCollaborator, $folder, Permission::DELETE_BOOKMARKS);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($suspendedCollaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'CollaboratorSuspended']);

        $this->assertCount(3, $folder->bookmarks);
    }

    #[Test]
    public function suspendedCollaboratorCanAddBookmarksToFolderWhenSuspensionDurationIsPast(): void
    {
        $this->loginUser($suspendedCollaborator = UserFactory::new()->create());

        $folder = FolderFactory::new()->create();
        $bookmarks = BookmarkFactory::times(3)->create();
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);
        $this->CreateCollaborationRecord($suspendedCollaborator, $folder, Permission::DELETE_BOOKMARKS);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder, suspensionDurationInHours: 1);

        $this->travel(57)->minutes(function () use ($folder, $bookmarksPublicIds) {
            $this->removeFolderBookmarksResponse([
                'bookmarks' => $bookmarksPublicIds->all(),
                'folder'    => $folder->public_id->present(),
            ])->assertForbidden()->assertJsonFragment(['message' => 'CollaboratorSuspended']);
        });

        $this->travel(62)->minutes(function () use ($folder, $bookmarksPublicIds) {
            $this->removeFolderBookmarksResponse([
                'bookmarks' => $bookmarksPublicIds->all(),
                'folder'    => $folder->public_id->present(),
            ])->assertOk();
        });

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
    }

    public function testWillReturnNotFoundResponseWhenBookmarksDoesNotExistsInFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($user)->create();

        //Assert will return not found when all bookmarks don't exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertNotFound()->assertJsonFragment($error = ['message' => "BookmarkNotFound"]);

        $this->addBookmarksToFolder($bookmarkIDs[0], $folder->id);

        //Assert will return not found when some (but not all) bookmarks exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertNotFound()->assertJsonFragment($error);

        $this->assertCount(1, $folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenBookmarkHasBeenDeleted(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);

        $bookmarks->first()->delete();

        $this->removeFolderBookmarksResponse([
            'folder'    => $folder->public_id->present(),
            'bookmarks' => $bookmarksPublicIds->all()
        ])->assertNotFound()->assertJsonFragment(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for(UserFactory::new())->create();
        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertCount(2, $folder->bookmarks);
    }

    private function addBookmarksToFolder(int|array $bookmarkIDs, int $folderID, array $hidden = []): void
    {
        $service = new CreateFolderBookmarks();

        $this->assertNotEmpty($bookmarkIDs);

        $service->create($folderID, $bookmarkIDs, $hidden);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $this->generateFolderId()->present()
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function test_user_with_permission_cannot_remove_bookmarks_when_folder_owner_has_deleted_account(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);
        $this->addBookmarksToFolder(BookmarkFactory::new()->create()->id, $folder->id);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$this->generateBookmarkId()->present()],
            'folder'    => $folder->public_id->present(),
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function testWillNotSendNotificationWhenBookmarksWereRemovedByFolderOwner(): void
    {
        $folderOwner = UserFactory::new()->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Notification::fake();

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillSendNotificationsWhenBookmarksWereNotRemovedByFolderOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        /** @var \App\Models\DatabaseNotification */
        $notification = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals(6, $notification->type->value);
        $this->assertEquals($notification->data, [
            'version'      => '1.0.0',
            'bookmarks'    => $bookmarks->map(fn (Bookmark $bookmark) => [
                'id'        => $bookmark->id,
                'url'       => $bookmark->url,
                'public_id' => $bookmark->public_id->value
            ])->all(),
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value,
            ],
            'collaborator'          => [
                'id'        => $collaborator->id,
                'public_id' => $collaborator->public_id->value,
                'full_name' => $collaborator->full_name->value,
                'profile_image_path' => null
            ],
        ]);
    }

    public function testWillNotSendNotificationsWhenNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenBookmarksRemovedNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableBookmarksRemovedNotification())
            ->create();

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder'    => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotNotifyFolderOwnerWhenCollaboratorIsMuted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds->all(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotifyFolderOwnerWhenMuteDurationIsPast(): void
    {
        Notification::fake();

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(3)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id, now(), 1);

        $this->loginUser($collaborator);
        $this->travel(61)->minutes(function () use ($bookmarksPublicIds, $folder) {
            $this->removeFolderBookmarksResponse([
                'bookmarks' => $bookmarksPublicIds->all(),
                'folder' => $folder->public_id->present()
            ])->assertOk();

            Notification::assertCount(1);
        });
    }

    #[Test]
    public function willReturnForbiddenWhenFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        $addBooksService = new CreateFolderBookmarks();

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarks = BookmarkFactory::new()->count(2)->for($folderOwner)->create();
        $bookmarkIDs = $bookmarks->pluck('id');
        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromObjects($bookmarks)->present();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $addBooksService->create($folder->id, $bookmarks->pluck('id')->all());

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        //Assert collaborator can remove bookmark when disabled action is not remove bookmark action
        $updateCollaboratorActionService->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarksPublicIds[0],
            'folder'    => $folder->public_id->present()
        ])->assertOk();

        $updateCollaboratorActionService->disable($folder->id, Feature::DELETE_BOOKMARKS);

        $this->removeFolderBookmarksResponse($query = ['bookmarks' => $bookmarksPublicIds[1], 'folder' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->removeFolderBookmarksResponse($query)->assertNotFound();

        $this->loginUser($folderOwner);
        $this->removeFolderBookmarksResponse($query)->assertOk();
    }

    #[Test]
    public function willNotLogActivityWhenFolderIsPrivateFolder(): void
    {
        $user = UserFactory::new()->create();

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $privateFolder = FolderFactory::new()->for($user)->private()->create();
        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();

        $this->addBookmarksToFolder($bookmark->id, $privateFolder->id);
        $this->addBookmarksToFolder($bookmark->id, $passwordProtectedFolder->id);

        $this->loginUser($user);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$bookmark->public_id->present()],
            'folder'    => $privateFolder->public_id->present()
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($user);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$bookmark->public_id->present()],
            'folder'    => $passwordProtectedFolder->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $privateFolder->activities);
        $this->assertCount(0, $passwordProtectedFolder->activities);
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $bookmarks = BookmarkFactory::times(2)->for($folderOwner)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableActivities(false))
            ->create();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks[0]->public_id->present(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->loginUser($folderOwner);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks[1]->public_id->present(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $folder->activities);
    }

    #[Test]
    public function willNotLogActivityWhenBookmarksRemovedActivityIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $bookmarks = BookmarkFactory::times(2)->for($folderOwner)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableBookmarkRemovedActivities(false))
            ->create();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks[0]->public_id->present(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->loginUser($folderOwner);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks[1]->public_id->present(),
            'folder' => $folder->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $folder->activities);
    }
}
