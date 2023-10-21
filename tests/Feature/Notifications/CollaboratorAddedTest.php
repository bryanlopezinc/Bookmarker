<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Illuminate\Support\Collection;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use App\Notifications\NewCollaboratorNotification;

class CollaboratorAddedTest extends TestCase
{
    use MakesHttpRequest;

    public function testFetchNotification(): void
    {
        [$folderOwner, $collaborator, $newCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new NewCollaboratorNotification(
                $newCollaborator->id,
                $folder->id,
                $collaborator->id
            )
        );

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonCount(3, 'data.0.attributes.new_collaborator')
            ->assertJsonCount(2, 'data.0.attributes.folder')
            ->assertJsonPath('data.0.type', 'CollaboratorAddedToFolderNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.new_collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.collaborator', function (array $collaboratorData) use ($collaborator) {
                $this->assertEquals($collaborator->id, $collaboratorData['id']);
                $this->assertEquals($collaborator->first_name, $collaboratorData['first_name']);
                $this->assertEquals($collaborator->last_name, $collaboratorData['last_name']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.folder', function (array $folderData) use ($folder) {
                $this->assertEquals($folder->name, $folderData['name']);
                $this->assertEquals($folder->id, $folderData['id']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.new_collaborator', function (array $newCollaboratorData)  use ($newCollaborator) {
                $this->assertEquals($newCollaborator->id, $newCollaboratorData['id']);
                $this->assertEquals($newCollaborator->first_name, $newCollaboratorData['first_name']);
                $this->assertEquals($newCollaborator->last_name, $newCollaboratorData['last_name']);
                return true;
            })
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "new_collaborator_exists",
                            "collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "new_collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
                            ],
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$folderOwner, $collaborator, $newCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new NewCollaboratorNotification(
                $newCollaborator->id,
                $folder->id,
                $collaborator->id
            )
        );

        $collaborator->delete();

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.0.attributes.by_collaborator');
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$folderOwner, $collaborator, $newCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new NewCollaboratorNotification(
                $newCollaborator->id,
                $folder->id,
                $collaborator->id
            )
        );

        $folder->delete();

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.0.attributes.folder');
    }
}
