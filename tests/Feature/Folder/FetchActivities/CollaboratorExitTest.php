<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\CollaboratorExitActivityLogData as ActivityLogData;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class CollaboratorExitTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::COLLABORATOR_EXIT);
    }

    #[Test]
    public function fetch(): void
    {
        /** @var \App\Models\User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator));

        $collaborator->update(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonPath('data.0.type', 'CollaboratorExitActivity')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow left')
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'event_time',
                            'message',
                            'collaborator' => [
                                'id',
                                'avatar',
                                'exists'
                            ],
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator));

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }
}
