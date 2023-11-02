<?php

namespace Tests\Feature\Notifications;

use App\Notifications\CollaboratorExitNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Illuminate\Notifications\DatabaseNotification;

class CollaboratorExitNotificationTest extends TestCase
{
    use MakesHttpRequest;

    public function testFetchNotification(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new CollaboratorExitNotification(
                $folder->id,
                $collaborator->id
            )
        );

        $expectedDateTime = DatabaseNotification::where('notifiable_id', $folderOwner->id)->sole(['created_at'])->created_at;

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'CollaboratorExitNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.collaborator', function (array $collaboratorData) use ($collaborator) {
                $this->assertEquals($collaborator->full_name, $collaboratorData['name']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.folder', function (array $folderData) use ($folder) {
                $this->assertEquals($folder->name, $folderData['name']);
                $this->assertEquals($folder->id, $folderData['id']);
                return true;
            })
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonCount(1, 'data.0.attributes.collaborator')
            ->assertJsonCount(2, 'data.0.attributes.folder')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            'notified_on',
                            "collaborator" =>  [
                                "name",
                            ],
                            "folder" => [
                                "name",
                                "id",
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
            new CollaboratorExitNotification(
                $folder->id,
                $collaborator->id
            )
        );

        $collaborator->delete();

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.0.attributes.by_collaborator');
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new CollaboratorExitNotification(
                $folder->id,
                $collaborator->id
            )
        );

        $folder->delete();

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.0.attributes.folder');
    }
}
