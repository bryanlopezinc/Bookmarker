<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\FolderVisibilityChangedToPublicActivityLogData as ActivityLogData;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class FolderVisibilityChangedToCollaboratorsOnlyActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(
            ActivityType::FOLDER_VISIBILITY_CHANGED_TO_COLLABORATORS_ONLY
        );
    }

    #[Test]
    public function fetch(): void
    {
        $folderOwner = UserFactory::new()->hasProfileImage()->create();
        $collaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->createFolderActivity->create($folder, new ActivityLogData($folderOwner));

        $folderOwner->update(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonPath('data.0.type', 'FolderVisibilityChangedToCollaboratorsOnlyActivity')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow changed folder visibility to collaborators')
            ->assertJsonPath('data.0.attributes.collaborator.id', $folderOwner->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($folderOwner->profile_image_path))
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
    public function whenVisibilityWasChangedByAuthUser(): void
    {
        /** @var \App\Models\User */
        $folderOwner = UserFactory::new()->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($folderOwner));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'You changed folder visibility to collaborators');
    }
}
