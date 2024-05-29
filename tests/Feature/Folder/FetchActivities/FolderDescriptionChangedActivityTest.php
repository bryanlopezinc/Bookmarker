<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData as ActivityLog;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class FolderDescriptionChangedActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::DESCRIPTION_CHANGED);
    }

    #[Test]
    public function fetch(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->hasProfileImage()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLog($collaborator, 'foo', 'bar'));

        $collaborator->update(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonPath('data.0.type', 'FolderDescriptionChangedActivity')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow changed folder description from foo to bar')
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
    public function whenDescriptionWasChangedFromBlankToFilled(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLog($collaborator, null, 'bar'));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow changed folder description to bar');
    }

    #[Test]
    public function whenDescriptionWasChangedFromFilledToBlank(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLog($collaborator, 'foo', null));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow removed folder description');
    }

    #[Test]
    public function whenCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLog($collaborator, 'foo', 'bar'));

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }

    #[Test]
    public function whenDescriptionWasChangedByAuthUser(): void
    {
        /** @var \App\Models\User */
        $folderOwner = UserFactory::new()->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLog($folderOwner, 'foo', 'bar'));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'You changed folder description from foo to bar');
    }
}
