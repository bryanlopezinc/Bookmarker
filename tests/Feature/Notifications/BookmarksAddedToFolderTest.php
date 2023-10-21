<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Database\Factories\BookmarkFactory;
use App\Notifications\BookmarksAddedToFolderNotification;

class BookmarksAddedToFolderTest extends TestCase
{
    use MakesHttpRequest;

    public function testFetchNotifications(): void
    {
        [$user, $tom] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($tom)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder->id, $tom->id);

        $user->notify($notification);

        Passport::actingAs($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'BookmarksAddedToFolderNotification')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.bookmarks_count', 3)
            ->assertJsonPath('data.0.attributes.by_collaborator', function (array $collaboratorData) use ($tom) {
                $this->assertEquals($tom->id, $collaboratorData['id']);
                $this->assertEquals($tom->first_name, $collaboratorData['first_name']);
                $this->assertEquals($tom->last_name, $collaboratorData['last_name']);
                return true;
            })
            ->assertJsonPath('data.0.attributes.folder', function (array $folderData) use ($folder) {
                $this->assertEquals($folder->name, $folderData['name']);
                $this->assertEquals($folder->id, $folderData['id']);
                return true;
            })
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "collaborator_exists",
                            "folder_exists",
                            "bookmarks_count",
                            "by_collaborator" =>  [
                                "id",
                                "first_name",
                                "last_name",
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
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder->id, $collaborator->id);

        $user->notify($notification);

        $collaborator->delete();

        Passport::actingAs($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonMissingPath('data.0.attributes.by_collaborator');
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder->id, $collaborator->id);

        $user->notify($notification);

        $folder->delete();

        Passport::actingAs($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonMissingPath('data.0.attributes.folder');
    }
}
