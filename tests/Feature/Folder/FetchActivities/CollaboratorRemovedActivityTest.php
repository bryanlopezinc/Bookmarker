<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\CollaboratorRemovedActivityLogData as ActivityLogData;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class CollaboratorRemovedActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::COLLABORATOR_REMOVED);
    }

    #[Test]
    public function fetch(): void
    {
        /** @var \App\Models\User */
        $folderOwner = UserFactory::new()->hasProfileImage()->create(['first_name' => 'bryan', 'last_name' => 'alex']);
        $collaborator = UserFactory::new()->hasProfileImage()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, $folderOwner));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonCount(3, 'data.0.attributes.collaborator_removed')
            ->assertJsonPath('data.0.type', 'CollaboratorRemovedActivity')
            ->assertJsonPath('data.0.attributes.message', 'You removed Jack Sparrow')
            ->assertJsonPath('data.0.attributes.collaborator.id', $folderOwner->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($folderOwner->profile_image_path))
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.collaborator_removed.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator_removed.avatar', (new ProfileImagesFilesystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.collaborator_removed.exists', true)
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
                            'collaborator_removed' => [
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
        [$otherCollaborator, $collaborator, $affectedCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($affectedCollaborator, $collaborator));

        $this->CreateCollaborationRecord($otherCollaborator, $folder);

        $collaborator->delete();

        $this->loginUser($otherCollaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }

    #[Test]
    public function whenAffectedCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$affectedCollaborator, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($affectedCollaborator, $collaborator));

        $this->CreateCollaborationRecord($collaborator, $folder);

        $affectedCollaborator->delete();

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator_removed.exists', false);
    }

    #[Test]
    public function whenCollaboratorWasNotRemovedByAuthUser(): void
    {
        /** @var \App\Models\User */
        [$affectedCollaborator, $collaborator, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($affectedCollaborator, $collaborator));

        $collaborator->update(['first_name' => 'bryan', 'last_name' => 'alex']);
        $affectedCollaborator->update(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Bryan Alex removed Jack Sparrow');
    }
}
