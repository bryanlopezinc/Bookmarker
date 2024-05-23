<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\InviteAcceptedActivityLogData as ActivityLogData;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class NewCollaboratorTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::NEW_COLLABORATOR);
    }

    #[Test]
    public function fetch(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->hasProfileImage()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($folderOwner, $collaborator));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonCount(3, 'data.0.attributes.new_collaborator')
            ->assertJsonPath('data.0.type', 'NewCollaboratorActivity')
            ->assertJsonPath('data.0.attributes.message', 'You added Jack Sparrow as a new collaborator')
            ->assertJsonPath('data.0.attributes.collaborator.id', $folderOwner->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($folderOwner->profile_image_path))
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.new_collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.new_collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.new_collaborator.exists', true)
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
                            'new_collaborator' => [
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
        [$newCollaborator, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, $newCollaborator));

        $this->CreateCollaborationRecord($newCollaborator, $folder);

        $collaborator->delete();

        $this->loginUser($newCollaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }

    #[Test]
    public function whenNewCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$newCollaborator, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, $newCollaborator));

        $this->CreateCollaborationRecord($collaborator, $folder);

        $newCollaborator->delete();

        $this->loginUser($collaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.new_collaborator.exists', false);
    }

    #[Test]
    public function whenCollaboratorWasNotAddedByAuthUser(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'bryan', 'last_name' => 'alex']);
        $newCollaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, $newCollaborator));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Bryan Alex added Jack Sparrow as a new collaborator');
    }

    #[Test]
    public function whenNewCollaboratorIsAuthUser(): void
    {
        $collaborator = UserFactory::new()->create(['first_name' => 'bryan', 'last_name' => 'alex']);
        $newCollaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, $newCollaborator));

        $this->CreateCollaborationRecord($newCollaborator, $folder);

        $this->loginUser($newCollaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Bryan Alex added you as a new collaborator');
        ;
    }
}
