<?php

namespace Tests\Feature\Folder;

use App\Actions\CreateFolderBookmarks;
use App\Actions\ToggleFolderFeature;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\Feature;
use App\Enums\Permission;
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
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class RemoveFolderBookmarksTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;

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
        $this->removeFolderBookmarksResponse(['folder' => 'foo'])->assertNotFound();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['folder' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->removeFolderBookmarksResponse(['bookmarks' => ['1', '2bar'], 'folder' => 55])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["bookmarks.1" => ["The bookmarks.1 attribute is invalid"]]);

        $this->removeFolderBookmarksResponse(['bookmarks' => ['1', '3', '4', '1'], 'folder' => 9])
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.3" => ["The bookmarks.3 field has a duplicate value."]
            ]);

        $this->removeFolderBookmarksResponse(['bookmarks' => range(1, 51), 'folder' => 54])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bookmarks' => 'The bookmarks must not have more than 50 items.']);
    }

    public function testRemoveBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(2)->for($user)->create()->pluck('id');

        $folder = FolderFactory::new()->for($user)->create(['updated_at' => now()->subDay()]);

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => [$bookmarkIDs[0]],
            'folder'    => $folder->id
        ])->assertSuccessful();

        $folder->load('bookmarks');

        $this->assertCount(1, $folder->bookmarks);
        $this->assertEquals($folder->bookmarks->first()->id, $bookmarkIDs[1]);

        //Assert the folder updated_at column was updated
        $this->assertTrue($folder->refresh()->updated_at->isToday());
    }

    public function testUserWithPermissionCanRemoveBookmarksFromFolder(): void
    {
        [$folderOwner, $user] = UserFactory::new()->count(2)->create();

        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->CreateCollaborationRecord($user, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($user);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder' => $folder->id
        ])->assertOk();

        $this->assertCount(0, $folder->bookmarks);
    }

    public function testUserWithRoleCanRemoveBookmarksFromFolder(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: [Permission::INVITE_USER, Permission::DELETE_BOOKMARKS]));

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder' => $folder->id
        ])->assertOk();

        $this->assertCount(0, $folder->bookmarks);
    }

    public function testWillReturnForbiddenWhenCollaboratorDoesNotHaveRemoveBookmarksPermissionOrRole(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::DELETE_BOOKMARKS->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->create(), permissions: Permission::DELETE_BOOKMARKS));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->for($folderOwner)->create(), permissions: Permission::DELETE_BOOKMARKS));

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folder->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(3, $folder->bookmarks);
    }

    public function willReturnForbiddenWhenCollaboratorRoleNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);
        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder, permissions: [Permission::DELETE_BOOKMARKS]));

        $role->delete();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folder->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(3, $folder->bookmarks);
    }

    public function testWillReturnNotFoundResponseWhenBookmarksDoesNotExistsInFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarkIDs = BookmarkFactory::new()->count(2)->for($user)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        //Assert will return not found when all bookmarks don't exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folder->id
        ])->assertNotFound()->assertJsonFragment($error = ['message' => "BookmarkNotFound"]);

        $this->addBookmarksToFolder($bookmarkIDs[0], $folder->id);

        //Assert will return not found when some (but not all) bookmarks exist in folder
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folder->id
        ])->assertNotFound()->assertJsonFragment($error);

        $this->assertCount(1, $folder->bookmarks);
    }

    public function testWillReturnNotFoundWhenBookmarkHasBeenDeleted(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);

        $bookmarks->first()->delete();

        $this->removeFolderBookmarksResponse([
            'folder'    => $folder->id,
            'bookmarks' => $bookmarks->pluck('id')->all()
        ])->assertNotFound()->assertJsonFragment(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $folder = FolderFactory::new()->for(UserFactory::new())->create();
        $this->addBookmarksToFolder($bookmarks->pluck('id')->all(), $folder->id);

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks->pluck('id')->all(),
            'folder'    => $folder->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertCount(2, $folder->bookmarks);
    }

    private function addBookmarksToFolder(int|array $bookmarkIDs, int $folderID): void
    {
        $service = new CreateFolderBookmarks();

        $this->assertNotEmpty($bookmarkIDs);

        $service->create($folderID, $bookmarkIDs);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(3)->for($user)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks->pluck('id')->all(),
            'folder'    => $folder->id + 1
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
            'bookmarks' => [1, 2],
            'folder'    => $folder->id,
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function testWillNotSendNotificationWhenBookmarksWereRemovedByFolderOwner(): void
    {
        $folderOwner = UserFactory::new()->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folderID = FolderFactory::new()->create(['user_id' => $folderOwner->id])->id;

        Notification::fake();

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folderID);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folderID
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillSendNotificationsWhenBookmarksWereNotRemovedByFolderOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarkIDs->all(),
            'folder' => $folder->id
        ])->assertOk();

        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals('BookmarksRemovedFromFolder', $notificationData->type);
        $this->assertEquals($notificationData->data, [
            'N-type'          => 'BookmarksRemovedFromFolder',
            'version'         => '1.0.0',
            'folder_id'       => $folder->id,
            'collaborator_id' => $collaborator->id,
            'bookmark_ids'    => $bookmarkIDs->all(),
            'full_name'       => $collaborator->full_name->value,
            'folder_name'     => $folder->name->value,
        ]);
    }

    public function testWillNotSendNotificationsWhenNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');

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
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folder->id
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenBookmarksRemovedNotificationsIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');

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
            'bookmarks' => $bookmarkIDs->all(),
            'folder'    => $folder->id
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotNotifyFolderOwnerWhenCollaboratorIsMuted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse(['bookmarks' => $bookmarkIDs->all(), 'folder' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotifyFolderOwnerWhenMuteDurationIsPast(): void
    {
        Notification::fake();

        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $bookmarkIDs = BookmarkFactory::times(3)->for($folderOwner)->create()->pluck('id');
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->addBookmarksToFolder($bookmarkIDs->all(), $folder->id);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folderOwner->id, now(), 1);

        $this->loginUser($collaborator);
        $this->travel(61)->minutes(function () use ($bookmarkIDs, $folder) {
            $this->removeFolderBookmarksResponse(['bookmarks' => $bookmarkIDs->all(), 'folder' => $folder->id])->assertOk();

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
        $bookmarks = BookmarkFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $addBooksService->create($folder->id, $bookmarks->pluck('id')->all());

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        //Assert collaborator can remove bookmark when disabled action is not remove bookmark action
        $updateCollaboratorActionService->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaborator);
        $this->removeFolderBookmarksResponse([
            'bookmarks' => $bookmarks[0]->id,
            'folder'    => $folder->id
        ])->assertOk();

        $updateCollaboratorActionService->disable($folder->id, Feature::DELETE_BOOKMARKS);

        $this->removeFolderBookmarksResponse($query = ['bookmarks' => $bookmarks[1]->id, 'folder' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->removeFolderBookmarksResponse($query)->assertNotFound();

        $this->loginUser($folderOwner);
        $this->removeFolderBookmarksResponse($query)->assertOk();
    }
}
