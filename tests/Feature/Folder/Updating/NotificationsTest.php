<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\Permission;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\Traits\ClearFoldersIconsStorage;

class NotificationsTest extends TestCase
{
    use CreatesCollaboration;
    use WithFaker;
    use ClearFoldersIconsStorage;

    #[Test]
    public function willNotifyFolderOwnerWhenCollaboratorUpdatesFolderName(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_NAME);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'      => $newName = 'bar',
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        $folder->refresh();

        /** @var \App\Models\DatabaseNotification */
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals(2, $notificationData->type->value);

        $expected = [
            'version' => '1.0.0',
            'from'    => $folder->name->value,
            'to'      => $newName,
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
        ];

        $this->assertEquals($notificationData->data, $expected);
    }

    #[Test]
    public function willNotifyFolderOwnerWhenCollaboratorUpdatesFolderDescription(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo', 'description' => 'foo bar folder']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_DESCRIPTION);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'description' => $newDescription = $this->faker->sentence,
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        $folder->refresh();

        /** @var \App\Models\DatabaseNotification */
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals(4, $notificationData->type->value);

        $expected = [
            'version'         => '1.0.0',
            'old_description' => $folder->description,
            'new_description' => $newDescription,
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
        ];

        $this->assertEquals($notificationData->data, $expected);
    }

    #[Test]
    public function willNotifyFolderOwnerWhenCollaboratorUpdatesFolderIcon(): void
    {
        $newIconPath = Str::random(40);

        Str::createRandomStringsUsing(fn () => $newIconPath);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_ICON);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'icon' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        $folder->refresh();

        /** @var \App\Models\DatabaseNotification */
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals(3, $notificationData->type->value);

        $expected = [
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
        ];

        $this->assertEquals($notificationData->data, $expected);
    }

    public function testWillNotSendNotificationWhenUpdateWasPerformedByFolderOwner(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create(['name' => 'foo', 'description' => 'foo bar folder']);

        Notification::fake();

        $this->loginUser($user);
        $this->updateFolderResponse([
            'name'        => 'bar',
            'description' => 'baz',
            'folder_id'   => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create(['name' => 'foo', 'description' => 'foo bar folder']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        Notification::fake();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'        => 'bar',
            'description' => 'baz',
            'folder_id'   => $folder->public_id->present(),
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenFolderUpdatedNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableFolderUpdatedNotification())
            ->create(['name' => 'foo', 'description' => 'foo bar folder']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        Notification::fake();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'        => 'bar',
            'description' => 'baz',
            'folder_id'   => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }
}
