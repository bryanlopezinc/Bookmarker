<?php

declare(strict_types=1);

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

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.type', 'CollaboratorAddedToFolderNotification')
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.message', 'Bruce Wayne added The Joker to Gotham Problems folder.')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.new_collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.folder.id', $folder->public_id->present())
            ->assertJsonPath('data.0.attributes.new_collaborator.id', $newCollaborator->public_id->present())
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
                            'new_collaborator' => [
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
        [$folderOwner, $collaborator, $newCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $folderOwner->notify(
            new NewCollaboratorNotification($newCollaborator, $folder, $collaborator)
        );

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborator.exists', false)
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
            ->assertJsonPath('data.0.attributes.folder.exists', false);
    }
}
