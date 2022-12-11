<?php

namespace Tests\Feature\Folder;

use App\Models\FolderCollaboratorPermission;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\DataTransferObjects\Builders\FolderSettingsBuilder as SettingsBuilder;
use Illuminate\Testing\Assert as PHPUnit;

class LeaveFolderCollaborationTest extends TestCase
{
    use WithFaker;

    protected function leaveFolderCollaborationResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('leaveFolderCollaboration'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborations/exit', 'leaveFolderCollaboration');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->leaveFolderCollaborationResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);
    }

    public function testWillThrowValidationWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse(['folder_id' => '2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "folder_id" => ["The folder_id attribute is invalid"],
            ]);
    }

    public function testExitCollaboration(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testWillOnlyLeaveSpecifiedFolder(): void
    {
        [$mark, $tony, $collaborator] = UserFactory::new()->count(3)->create();
        $marksFolder = FolderFactory::new()->for($mark)->create();
        $tonysFolder = FolderFactory::new()->for($tony)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($marksFolder->id)->create();
        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($tonysFolder->id)->create();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $marksFolder->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $marksFolder->id
        ]);

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'user_id' => $collaborator->id,
            'folder_id' => $tonysFolder->id
        ]);
    }

    public function testWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create()->id
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testWhenFolderDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create()->id + 1
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'The folder does not exists'
            ]);
    }

    public function testCannotExitFromOwnFolder(): void
    {
        Passport::actingAs($folderOwner = UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->for($folderOwner)->create()->id
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot exit from own folder'
            ]);
    }

    public function testWillNotHaveAccessToFolderAfterAction(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $factory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id);
        $collaboratorBookmarks = BookmarkFactory::new()->count(2)->create();

        $factory->addBookmarksPermission()->create();
        $factory->inviteUser()->create();
        $factory->removeBookmarksPermission()->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $this->postJson(route('addBookmarksToFolder'), [
            'bookmarks' => $collaboratorBookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();

        $this->deleteJson(route('removeBookmarksFromFolder'), [
            'bookmarks' => $collaboratorBookmarks->pluck('id')->implode(','),
            'folder' => $folder->id
        ])->assertForbidden();

        $this->postJson(route('sendFolderCollaborationInvite'), [
            'email' => UserFactory::new()->create()->email,
            'folder_id' => $folder->id,
        ])->assertForbidden();

        $this->getJson(route('folderBookmarks', [
            'folder_id' => $folder->id
        ]))->assertForbidden();
    }

    public function testFolderWillNotShowInUserCollaborations(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $this->getJson(route('fetchUserCollaborations'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testFolderOwnerWillNotSeeUserAsCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($folderOwner);
        $this->getJson(route('fetchFolderCollaborators', [
            'folder_id' => $folder->id
        ]))->assertOk()
            ->assertJsonCount(1, 'data');

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        Passport::actingAs($folderOwner);
        $this->getJson(route('fetchFolderCollaborators', [
            'folder_id' => $folder->id
        ]))->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotifyFolderOwnerWhenUserExits(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folderOwner->id)->sole(['data'])->data;

        PHPUnit::assertArraySubset([
            'exited_from_folder' => $folder->id,
            'exited_by' => $collaborator->id,
        ], $notificationData);
    }

    public function testWillNotNotifyFolderOwner_whenNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->setting(fn (SettingsBuilder $b) => $b->disableNotifications())
            ->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->setting(fn (SettingsBuilder $b) => $b->disableNotifications())
            ->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->addBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->setting(fn (SettingsBuilder $b) => $b->disableCollaboratorExitNotification())
            ->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->disableCollaboratorExitNotification())
            ->for($folderOwner)
            ->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->addBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorDoesNotHaveWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->enableOnlyCollaboratorWithWritePermissionNotification())
            ->for($folderOwner)
            ->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->viewBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwner_whenCollaboratorHasWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()
            ->setting(fn (SettingsBuilder $b) => $b->enableOnlyCollaboratorWithWritePermissionNotification())
            ->for($folderOwner)
            ->create();

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->removeBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertTimesSent(1, \App\Notifications\CollaboratorExitNotification::class);
    }
}
