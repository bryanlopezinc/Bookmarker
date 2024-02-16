<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Database\Factories\BookmarkFactory;
use App\Notifications\BookmarksAddedToFolderNotification;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class BookmarksAddedToFolderTest extends TestCase
{
    use MakesHttpRequest;
    use WithFaker;

    public function testFetchNotifications(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder, $collaborator);

        $user->notify($notification);

        $collaborator->update(['first_name' => 'bryan', 'last_name' => 'benz']);
        $folder->update(['name' => 'my awesome bookmarks']);

        $expectedDateTime = $user->notifications()->sole(['created_at'])->created_at;

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.type', 'BookmarksAddedToFolderNotification')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.message', "Bryan Benz added 3 bookmarks to My Awesome Bookmarks folder.")
            ->assertJsonPath('data.0.attributes.notified_on', fn (string $dateTime) => $dateTime === (string) $expectedDateTime)
            ->assertJsonPath('data.0.attributes.collaborator_id', $collaborator->id)
            ->assertJsonPath('data.0.attributes.folder_id', $folder->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'collaborator_exists',
                            'folder_exists',
                            'message',
                            'notified_on',
                            'collaborator_id',
                            'folder_id',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenOneBookmarkWasAdded(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification([$bookmark->id], $folder, $collaborator);

        $user->notify($notification);

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added 1 bookmark to {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder, $collaborator);

        $user->notify($notification);

        $collaborator->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added 3 bookmarks to {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder, $collaborator);

        $user->notify($notification);

        $folder->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added 3 bookmarks to {$folder->name->present()} folder.");
    }
}
