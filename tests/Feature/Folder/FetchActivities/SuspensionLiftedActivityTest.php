<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\SuspensionLiftedActivityLogData as ActivityLogData;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class SuspensionLiftedActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::SUSPENSION_LIFTED);
    }

    #[Test]
    public function fetch(): void
    {
        $folderOwner = UserFactory::new()->create();
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
            ->assertJsonCount(3, 'data.0.attributes.affected_collaborator')
            ->assertJsonPath('data.0.type', 'SuspensionLiftedActivity')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow suspension was lifted by you')
            ->assertJsonPath('data.0.attributes.collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($folderOwner->profile_image_path))
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.affected_collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.affected_collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.affected_collaborator.exists', true)
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
                            'affected_collaborator' => [
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
        [$affectedCollaborator, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($affectedCollaborator, $collaborator));

        $this->CreateCollaborationRecord($affectedCollaborator, $folder);

        $collaborator->delete();

        $this->loginUser($affectedCollaborator);
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
            ->assertJsonPath('data.0.attributes.affected_collaborator.exists', false);
    }

    #[Test]
    public function whenCollaboratorSuspensionWasNotLiftedByAuthUser(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'bryan', 'last_name' => 'alex']);
        $affectedCollaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($affectedCollaborator, $collaborator));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow suspension was lifted by Bryan Alex');
    }

    #[Test]
    public function whenAffectedCollaboratorIsAuthUser(): void
    {
        $collaborator = UserFactory::new()->create(['first_name' => 'bryan', 'last_name' => 'alex']);
        $affectedCollaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($affectedCollaborator, $collaborator));

        $this->CreateCollaborationRecord($affectedCollaborator, $folder);

        $this->loginUser($affectedCollaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Your suspension was lifted by Bryan Alex');
    }
}
