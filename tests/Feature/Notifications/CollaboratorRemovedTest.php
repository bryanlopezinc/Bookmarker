<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Notifications\CollaboratorRemovedNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;

class CollaboratorRemovedTest extends TestCase
{
    use MakesHttpRequest;

    #[Test]
    public function fetchNotifications(): void
    {
        [$folderOwner, $collaborator, $collaboratorRemoved] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $notification = new CollaboratorRemovedNotification($folder, $collaboratorRemoved, $collaborator, false);

        $folder->update(['name' => 'tech problems']);
        $folderOwner->notify($notification);

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'CollaboratorRemovedNotification')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder.id', $folder->public_id->present())
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaboratorRemoved->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.removed_by.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.removed_by.exists', true)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed {$collaboratorRemoved->full_name->present()} from Tech Problems.")
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'folder'       => ['id', 'exists',],
                            'collaborator' => ['id', 'exists',],
                            'removed_by'   => ['id', 'exists',],
                            'notified_on',
                            'message',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenCollaboratorWasBanned(): void
    {
        [$folderOwner, $collaborator, $collaboratorRemoved] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $notification = new CollaboratorRemovedNotification($folder, $collaboratorRemoved, $collaborator, true);

        $folder->update(['name' => 'tech problems']);
        $folderOwner->notify($notification);

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed and banned {$collaboratorRemoved->full_name->present()} from Tech Problems.");
    }

    #[Test]
    public function willReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$folderOwner, $collaborator, $collaboratorRemoved] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $notification = new CollaboratorRemovedNotification($folder, $collaboratorRemoved, $collaborator, false);

        $folder->update(['name' => 'tech problems']);
        $collaborator->notify($notification);

        $folder->delete();

        $this->loginUser($collaborator);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder.exists', false)
            ->assertJsonPath('data.0.attributes.message', function (string $message) use ($folder) {
                $this->assertStringContainsString($folder->name->present(), $message);
                return true;
            });
    }
}
