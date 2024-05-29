<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\CollaboratorSuspendedActivityLogData as ActivityLogData;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class SuspensionActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::USER_SUSPENDED);
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
            ->assertJsonCount(3, 'data.0.attributes.suspended_by')
            ->assertJsonPath('data.0.type', 'CollaboratorSuspendedActivity')
            ->assertJsonPath('data.0.attributes.message', 'You suspended Jack Sparrow')
            ->assertJsonPath('data.0.attributes.suspended_by.id', $folderOwner->public_id->present())
            ->assertJsonPath('data.0.attributes.suspended_by.avatar', (new ProfileImagesFilesystem())->publicUrl($folderOwner->profile_image_path))
            ->assertJsonPath('data.0.attributes.suspended_by.exists', true)
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
                            'suspended_by' => [
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
    public function whenCollaboratorWasNotSuspendedIndefinitely(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, $folderOwner, 5));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'You suspended Jack Sparrow for 5 hours');
    }

    #[Test]
    public function whenCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$suspendedCollaborator, $collaborator, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($suspendedCollaborator, $collaborator));

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.suspended_by.exists', false);
    }

    #[Test]
    public function whenSuspendedCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$suspendedCollaborator, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($suspendedCollaborator, $collaborator));

        $this->CreateCollaborationRecord($collaborator, $folder);

        $suspendedCollaborator->delete();

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }

    #[Test]
    public function whenCollaboratorWasNotSuspendedByAuthUser(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);
        $suspendedCollaborator = UserFactory::new()->create(['first_name' => 'bryan', 'last_name' => 'alex']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($suspendedCollaborator, $collaborator));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow suspended Bryan Alex');
    }

    #[Test]
    public function whenSuspendedCollaboratorIsAuthUser(): void
    {
        $suspendedCollaborator = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'bryan', 'last_name' => 'alex']);
        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($suspendedCollaborator, $collaborator));

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        $this->loginUser($suspendedCollaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Bryan Alex suspended you');
    }
}
