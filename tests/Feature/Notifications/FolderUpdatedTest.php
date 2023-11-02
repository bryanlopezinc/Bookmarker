<?php

namespace Tests\Feature\Notifications;

use App\Models\Folder;
use App\Notifications\FolderUpdatedNotification;
use Tests\TestCase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;

class FolderUpdatedTest extends TestCase
{
    use MakesHttpRequest, WithFaker;

    public function testFetchNotification(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);
        $folder->name = 'baz';

        $folderOwner->notify(
            new FolderUpdatedNotification(
                $folder,
                $collaborator->id
            )
        );

        $expectedDateTime = DatabaseNotification::where('notifiable_id', $folderOwner->id)->sole(['created_at'])->created_at;

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'FolderUpdatedNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.collaborator', function (array $collaboratorData) use ($collaborator) {
                $this->assertEquals($collaborator->id, $collaboratorData['id']);
                $this->assertEquals($collaborator->full_name, $collaboratorData['name']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.folder', function (array $folderData) use ($folder) {
                $this->assertEquals('foo', $folderData['name']);
                $this->assertEquals($folder->id, $folderData['id']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.changes', function (array $folderChanges) {
                $this->assertCount(1, $folderChanges);
                $this->assertEquals($folderChanges['name']['from'], 'foo');
                $this->assertEquals($folderChanges['name']['to'], 'baz');
                return true;
            })
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonCount(2, 'data.0.attributes.collaborator')
            ->assertJsonCount(1, 'data.0.attributes.changes')
            ->assertJsonCount(2, 'data.0.attributes.changes.name')
            ->assertJsonCount(2, 'data.0.attributes.folder')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            'notified_on',
                            "collaborator" =>  [
                                "id",
                                "name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "changes" =>  [
                                "name" => [
                                    'from',
                                    'to'
                                ],
                            ],
                        ]
                    ]
                ]
            ]);
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
                $collaborator->id
            )
        );

        $folder->delete();

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
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
                $collaborator->id
            )
        );

        $collaborator->delete();

        Passport::actingAs($folderOwner);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.0.attributes.by_collaborator');
    }
}
