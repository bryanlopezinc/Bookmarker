<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Database\Factories\BookmarkFactory;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use Illuminate\Notifications\DatabaseNotification;

class BookmarksRemovedFromFolderTest extends TestCase
{
    use MakesHttpRequest;

    public function testFetchNotifications(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification(
                $collaboratorBookmarks->all(),
                $folder->id,
                $collaborator->id
            )
        );

        $expectedDateTime = DatabaseNotification::where('notifiable_id', $user->id)->sole(['created_at'])->created_at;

        Passport::actingAs($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'BookmarksRemovedFromFolderNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.bookmarks_count', 3)
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.by_collaborator', function (array $collaboratorData) use ($collaborator) {
                $this->assertEquals($collaborator->id, $collaboratorData['id']);
                $this->assertEquals($collaborator->full_name, $collaboratorData['name']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.folder', function (array $folderData) use ($folder) {
                $this->assertEquals($folder->name, $folderData['name']);
                $this->assertEquals($folder->id, $folderData['id']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.bookmarks', function (array $bookmarks) {
                $this->assertCount(3, $bookmarks);
                return true;
            })
            ->assertJsonCount(8, 'data.0.attributes')
            ->assertJsonCount(2, 'data.0.attributes.by_collaborator')
            ->assertJsonCount(2, 'data.0.attributes.folder')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "bookmarks_count",
                            'notified_on',
                            "by_collaborator" =>  [
                                "id",
                                "name",
                            ],
                            "folder" => [
                                "name",
                                "id",
                            ],
                            "bookmarks" => [
                                '*' => ['title']
                            ]
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification(
                $collaboratorBookmarks->all(),
                $folder->id,
                $collaborator->id
            )
        );

        $collaborator->delete();

        Passport::actingAs($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.0.attributes.by_collaborator');
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification(
                $collaboratorBookmarks->all(),
                $folder->id,
                $collaborator->id
            )
        );

        $folder->delete();

        Passport::actingAs($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.0.attributes.folder');
    }
}
