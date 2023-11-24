<?php

namespace Tests\Feature\Folder;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Models\FolderCollaboratorPermission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Enums\Permission;
use App\Models\FolderCollaborator;
use Illuminate\Testing\Assert as PHPUnit;
use Tests\Traits\CreatesCollaboration;

class LeaveFolderCollaborationTest extends TestCase
{
    use WithFaker, CreatesCollaboration;

    protected function leaveFolderCollaborationResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('leaveFolderCollaboration'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborations/exit', 'leaveFolderCollaboration');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->leaveFolderCollaborationResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);

        $this->leaveFolderCollaborationResponse(['folder_id' => '2bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["folder_id" => ["The folder_id attribute is invalid"]]);
    }

    public function testExit(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertDatabaseMissing(FolderCollaborator::class, [
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id
        ]);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create()->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->create()->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnForbiddenWenFolderBelongsToUser(): void
    {
        Passport::actingAs($folderOwner = UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse([
            'folder_id' =>  FolderFactory::new()->for($folderOwner)->create()->id
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotExitOwnFolder']);
    }

    public function testWillNotifyFolderOwnerWhenUserExits(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $folder->id
        ])->assertOk();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folderOwner->id)->sole(['data'])->data;

        PHPUnit::assertArraySubset([
            'exited_from_folder' => $folder->id,
            'exited_by'          => $collaborator->id,
        ], $notificationData);
    }

    public function testWillNotNotifyFolderOwner_whenNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

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
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $settings = FolderSettingsBuilder::new()
            ->disableCollaboratorExitNotification()
            ->enableOnlyCollaboratorWithWritePermissionNotification();

        $folder = FolderFactory::new()->for($folderOwner)->settings($settings)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableCollaboratorExitNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorDoesNotHaveWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableOnlyCollaboratorWithWritePermissionNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwner_whenCollaboratorHasWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableOnlyCollaboratorWithWritePermissionNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertSentTimes(\App\Notifications\CollaboratorExitNotification::class, 1);
    }
}
