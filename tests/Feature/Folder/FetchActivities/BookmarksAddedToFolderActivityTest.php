<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\FetchActivities;

use App\Actions\CreateFolderActivity;
use App\Enums\ActivityType;
use App\Filesystem\ProfileImagesFilesystem;
use App\DataTransferObjects\Activities\NewFolderBookmarksActivityLogData as ActivityLogData;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Before;

class BookmarksAddedToFolderActivityTest extends TestCase
{
    private CreateFolderActivity $createFolderActivity;

    #[Before]
    public function setCreateActivity(): void
    {
        $this->createFolderActivity = new CreateFolderActivity(ActivityType::NEW_BOOKMARKS);
    }

    #[Test]
    public function fetch(): void
    {
        $folderOwner = UserFactory::new()->hasProfileImage()->create(['first_name' => 'bryan', 'last_name' => 'alex']);

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $bookmark = BookmarkFactory::new()->create();

        $this->createFolderActivity->create($folder, new ActivityLogData(collect([$bookmark]), $folderOwner));

        $this->loginUser($folderOwner);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonPath('data.0.type', 'NewFolderBookmarksActivity')
            ->assertJsonPath('data.0.attributes.message', 'You added 1 new bookmark')
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
    public function whenCollaboratorNoLongerExist(): void
    {
        $collaborator = UserFactory::new()->create(['first_name' => 'jack', 'last_name' => 'sparrow']);
        $otherCollaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();
        $bookmarks = BookmarkFactory::times(2)->create();

        $this->createFolderActivity->create($folder, new ActivityLogData($bookmarks, $collaborator));

        $this->CreateCollaborationRecord($otherCollaborator, $folder);

        $collaborator->delete();

        $this->loginUser($otherCollaborator);
        $this->fetchActivitiesTestResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', 'Jack Sparrow added 2 new bookmarks')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false);
    }
}
