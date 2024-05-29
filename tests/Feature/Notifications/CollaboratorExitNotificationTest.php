<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Notifications\CollaboratorExitNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;

class CollaboratorExitNotificationTest extends TestCase
{
    use MakesHttpRequest;

    public function testFetchNotification(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new CollaboratorExitNotification($folder, $collaborator)
        );

        $collaborator->update(['first_name' => 'bruce', 'last_name' => 'wayne']);

        $folder->update(['name' => 'gotham problems']);

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'CollaboratorExitNotification')
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.message', 'Bruce Wayne left Gotham Problems folder.')
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.folder.id', $folder->public_id->present())
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'message',
                            'notified_on',
                            'collaborator' => [
                                'id',
                                'exists'
                            ],
                            'folder' => [
                                'id',
                                'exists',
                            ],
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new CollaboratorExitNotification($folder, $collaborator)
        );

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborator.exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} left {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new CollaboratorExitNotification(
                $folder,
                $collaborator
            )
        );

        $folder->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder.exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} left {$folder->name->present()} folder.");
    }
}
