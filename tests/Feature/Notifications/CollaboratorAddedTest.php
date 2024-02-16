<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
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
            new NewCollaboratorNotification($newCollaborator, $folder, $collaborator)
        );

        $collaborator->update(['first_name' => 'bruce', 'last_name' => 'wayne']);
        $newCollaborator->update(['first_name' => 'the', 'last_name' => 'joker']);

        $folder->update(['name' => 'gotham problems']);

        $expectedDateTime = $folderOwner->notifications()->sole(['created_at'])->created_at;

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(9, 'data.0.attributes')
            ->assertJsonPath('data.0.type', 'CollaboratorAddedToFolderNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.message', 'Bruce Wayne added The Joker to Gotham Problems folder.')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.new_collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.collaborator_id', $collaborator->id)
            ->assertJsonPath('data.0.attributes.folder_id', $folder->id)
            ->assertJsonPath('data.0.attributes.new_collaborator_id', $newCollaborator->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'collaborator_exists',
                            'folder_exists',
                            'new_collaborator_exists',
                            'message',
                            'notified_on',
                            'collaborator_id',
                            'folder_id',
                            'new_collaborator_id',
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
            new NewCollaboratorNotification($newCollaborator, $folder, $collaborator)
        );

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added {$newCollaborator->full_name->present()} to {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$folderOwner, $collaborator, $newCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new NewCollaboratorNotification($newCollaborator, $folder, $collaborator)
        );

        $folder->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder_exists', false);
    }
}
