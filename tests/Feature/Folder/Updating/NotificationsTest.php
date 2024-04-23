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

class NotificationsTest extends TestCase
{
    use CreatesCollaboration;
    use WithFaker;

    #[Test]
    public function willNotifyFolderOwnerWhenCollaboratorUpdatesFolderName(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_NAME);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'      => $newName = $this->faker->word,
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        $folder->refresh();
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals('FolderUpdated', $notificationData->type);

        $expected = [
            'N-type'          => 'FolderUpdated',
            'version'         => '1.0.0',
            'modified'         => 'name',
            'changes'         => [
                'from' => $folder->name->value,
                'to'   => $newName,
            ],
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value
            ],
            'collaborator' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value
            ]
        ];

        $this->assertEquals($notificationData->data, $expected);
    }

    #[Test]
    public function willNotifyFolderOwnerWhenCollaboratorUpdatesFolderDescription(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_DESCRIPTION);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'description' => $newDescription = $this->faker->sentence,
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        $folder->refresh();
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals('FolderUpdated', $notificationData->type);

        $expected = [
            'N-type'          => 'FolderUpdated',
            'version'         => '1.0.0',
            'modified'         => 'description',
            'changes'         => [
                'from' => $folder->description,
                'to'   => $newDescription,
            ],
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value
            ],
            'collaborator' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value
            ]
        ];

        $this->assertEquals($notificationData->data, $expected);
    }

    #[Test]
    public function willNotifyFolderOwnerWhenCollaboratorUpdatesFolderThumbnail(): void
    {
        $newIconPath = Str::random(40);

        Str::createRandomStringsUsing(fn () => $newIconPath);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_THUMBNAIL);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'thumbnail' => UploadedFile::fake()->image('folderIcon.jpg')->size(2000),
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        $folder->refresh();
        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals('FolderUpdated', $notificationData->type);

        $expected = [
            'N-type'          => 'FolderUpdated',
            'version'         => '1.0.0',
            'modified'         => 'icon_path',
            'changes'         => [
                'to' => "{$newIconPath}.jpg",
            ],
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value
            ],
            'collaborator' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value
            ]
        ];

        $this->assertEquals($notificationData->data, $expected);
    }

    public function testWillNotSendNotificationWhenUpdateWasPerformedByFolderOwner(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        Notification::fake();

        $this->loginUser($user);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
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
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        Notification::fake();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id' => $folder->public_id->present(),
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenFolderUpdatedNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableFolderUpdatedNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        Notification::fake();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSent();
    }
}
