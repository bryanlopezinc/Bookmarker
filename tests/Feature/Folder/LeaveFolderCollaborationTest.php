<?php

namespace Tests\Feature\Folder;

use App\Models\FolderCollaboratorPermission;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Enums\FolderSettingKey;
use App\Models\FolderSetting;
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

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

        Passport::actingAs($collaborator);

        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id'   => $collaborator->id,
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

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();

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
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::ENABLE_NOTIFICATIONS->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::ENABLE_NOTIFICATIONS->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->addBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::NOTIFY_ON_COLLABORATOR_EXIT->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::NOTIFY_ON_COLLABORATOR_EXIT->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->addBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorDoesNotHaveWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION->value,
            'value'     => true,
            'folder_id' => $folder->id
        ]);

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->viewBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwner_whenCollaboratorHasWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION->value,
            'value'     => true,
            'folder_id' => $folder->id
        ]);

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->removeBookmarksPermission()->create();
        Notification::fake();

        Passport::actingAs($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->id])->assertOk();

        Notification::assertTimesSent(1, \App\Notifications\CollaboratorExitNotification::class);
    }
}
