<?php

namespace Tests\Feature\Notifications;

use App\Filesystem\ProfileImageFileSystem;
use App\Models\Folder;
use App\Notifications\FolderUpdatedNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use PHPUnit\Framework\Attributes\Test;

class FolderUpdatedTest extends TestCase
{
    use MakesHttpRequest, WithFaker;

    #[Test]
    public function nameUpdatedNotification(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->hasProfileImage()->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderUpdatedNotification(
                $folder,
                $collaborator->id,
                'name',
            )
        );

        $expectedDateTime = DatabaseNotification::where('notifiable_id', $folderOwner->id)->sole(['created_at'])->created_at;

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'FolderUpdatedNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.modified', 'name')
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.collaborator', function (array $collaboratorData) use ($collaborator) {
                $this->assertEquals($collaborator->id, $collaboratorData['id']);
                $this->assertEquals($collaborator->full_name, $collaboratorData['name']);
                $this->assertEquals((new ProfileImageFileSystem())->publicUrl($collaborator->profile_image_path), $collaboratorData['profile_image_url']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.folder', function (array $folderData) use ($folder) {
                $this->assertEquals('foo', $folderData['name']);
                $this->assertEquals($folder->id, $folderData['id']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.changes', function (array $folderChanges) {
                $this->assertCount(2, $folderChanges);
                $this->assertEquals($folderChanges['from'], 'foo');
                $this->assertEquals($folderChanges['to'], 'baz');
                return true;
            })
            ->assertJsonCount(8, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.collaborator')
            ->assertJsonCount(2, 'data.0.attributes.changes')
            ->assertJsonCount(2, 'data.0.attributes.folder')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "modified",
                            "collaborator_exists",
                            "folder_exists",
                            'notified_on',
                            "collaborator" =>  [
                                "id",
                                "name",
                                "profile_image_url"
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "changes" =>  [
                                'from',
                                'to',
                            ],
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function descriptionUpdatedNotification(): void
    {
        $folderOwner = UserFactory::new()->create();
        $collaborator = UserFactory::new()->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['description' => 'foo']);
        $folder->description = 'baz';

        $folderOwner->notify(
            new FolderUpdatedNotification(
                $folder,
                $collaborator->id,
                'description',
            )
        );

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.modified', 'description')
            ->assertJsonPath('data.0.attributes.changes', function (array $folderChanges) {
                $this->assertCount(2, $folderChanges);
                $this->assertEquals($folderChanges['from'], 'foo');
                $this->assertEquals($folderChanges['to'], 'baz');
                return true;
            });
    }

    public function testWillReturnCorrectPayloadWhenFolderDoesNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderUpdatedNotification(
                $folder,
                $collaborator->id,
                'name'
            )
        );

        $folder->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.0.attributes.folder');
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderUpdatedNotification(
                $folder,
                $collaborator->id,
                'name'
            )
        );

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.0.attributes.by_collaborator');
    }
}
