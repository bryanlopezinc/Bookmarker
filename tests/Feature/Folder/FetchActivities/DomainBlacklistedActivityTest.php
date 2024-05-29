<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\DomainBlacklistedActivityLogData as ActivityLogData;
use App\ValueObjects\Url;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class DomainBlacklistedActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::DOMAIN_BLACKLISTED);
    }

    #[Test]
    public function whenActionWasPerformedByFolderOwner(): void
    {
        /** @var \App\Models\User */
        $folderOwner = UserFactory::new()->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($folderOwner, new Url('https://youtube.com/contents')));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonPath('data.0.type', 'DomainBlacklistedActivity')
            ->assertJsonPath('data.0.attributes.message', 'You restricted bookmarks from youtube.com.')
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
    public function whenActionWasNotPerformedByFolderOwner(): void
    {
        /** @var \App\Models\User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, new Url('https://youtube.com/contents')));

        $collaborator->update(['first_name' => 'chris', 'last_name' => 'evans']);

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.message', 'Chris Evans restricted bookmarks from youtube.com.')
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.collaborator.avatar', (new ProfileImagesFilesystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.collaborator.exists', true);
    }

    #[Test]
    public function whenCollaboratorNoLongerExist(): void
    {
        /** @var \App\Models\User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($collaborator, new Url('https://youtube.com/contents')));

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }
}
