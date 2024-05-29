<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\DataTransferObjects\Activities\CollaboratorExitActivityLogData;
use App\Enums\ActivityType;
use App\Enums\CollaboratorExitNotificationMode as Mode;
use App\Models\FolderCollaboratorPermission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use App\Enums\Permission;
use App\FolderSettings\Settings\Activities\LogActivities;
use App\FolderSettings\Settings\Notifications\CollaboratorExitNotification;
use App\FolderSettings\Settings\Notifications\CollaboratorExitNotificationMode;
use App\FolderSettings\Settings\Notifications\Notifications;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\GeneratesId;

class LeaveFolderCollaborationTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use GeneratesId;

    protected function leaveFolderCollaborationResponse(array $parameters = []): TestResponse
    {
        $folderId = $parameters['folder_id'];

        unset($parameters['folder_id']);

        return $this->deleteJson(route('leaveFolderCollaboration', ['folder_id' => $folderId]), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborations/{folder_id}', 'leaveFolderCollaboration');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->leaveFolderCollaborationResponse(['folder_id' => 3])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse(['folder_id' => '2bar'])
            ->assertNotFound()
            ->assertJsonFragment(['FolderNotFound']);
    }

    public function testExit(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'folder_id' => $folder->id,
            'user_id'   => $collaborator->id,
        ]);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertEquals($activity->type, ActivityType::COLLABORATOR_EXIT);
        $this->assertEquals($activity->data, (new CollaboratorExitActivityLogData($collaborator))->toArray());
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse(['folder_id' =>  FolderFactory::new()->create()->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->leaveFolderCollaborationResponse([
            'folder_id' => $this->generateFolderId()->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnForbiddenWenFolderBelongsToUser(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $this->leaveFolderCollaborationResponse(['folder_id' =>  FolderFactory::new()->for($folderOwner)->create()->public_id->present()])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotExitOwnFolder']);
    }

    public function testWillNotifyFolderOwnerWhenUserExits(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        /** @var \App\Models\DatabaseNotification */
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals(9, $notificationData->type->value);
        $this->assertEquals($notificationData->data, [
            'version' => '1.0.0',
            'folder'  => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value
            ],
            'collaborator' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value,
                'profile_image_path' => null
            ]
        ]);
    }

    public function testWillNotNotifyFolderOwner_whenNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new Notifications(false))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new Notifications(false))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $settings = [
            new CollaboratorExitNotification(false),
            new CollaboratorExitNotificationMode(Mode::HAS_WRITE_PERMISSION->value)
        ];

        $folder = FolderFactory::new()->for($folderOwner)->settings($settings)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorExitNotificationsAreDisabled_andCollaboratorHasWritePermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new CollaboratorExitNotification(false))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwner_whenCollaboratorDoesNotHaveWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new CollaboratorExitNotificationMode(Mode::HAS_WRITE_PERMISSION->value))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwner_whenCollaboratorHasWritePermission_and_onlyWhenCollaboratorHasWritePermissionNotificationIsEnabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new CollaboratorExitNotificationMode(Mode::HAS_WRITE_PERMISSION->value))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::DELETE_BOOKMARKS);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        Notification::assertSentTimes(\App\Notifications\CollaboratorExitNotification::class, 1);
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new LogActivities(false))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->leaveFolderCollaborationResponse(['folder_id' => $folder->public_id->present()])->assertOk();

        $this->assertCount(0, $folder->activities);
    }
}
