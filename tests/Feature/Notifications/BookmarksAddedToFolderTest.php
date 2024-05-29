<?php

declare(strict_types=1);

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
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder, $collaborator);

        $user->notify($notification);

        $collaborator->update(['first_name' => 'bryan', 'last_name' => 'benz']);
        $folder->update(['name' => 'my awesome bookmarks']);

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonCount(2, 'data.0.attributes.collaborator')
            ->assertJsonCount(2, 'data.0.attributes.folder')
            ->assertJsonPath('data.0.type', 'BookmarksAddedToFolderNotification')
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.message', "Bryan Benz added 3 bookmarks to My Awesome Bookmarks folder.")
            ->assertJsonPath('data.0.attributes.collaborator.id', $collaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.folder.id', $folder->public_id->present())
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'message',
                            'notified_on',
                            'collaborator' => [
                                'id',
                                'exists'
                            ],
                            'folder' => [
                                'id',
                                'exists',
                            ],
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

        $notification = new BookmarksAddedToFolderNotification([$bookmark], $folder, $collaborator);

        $user->notify($notification);

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added 1 bookmark to {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder, $collaborator);

        $user->notify($notification);

        $collaborator->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.collaborator.exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added 3 bookmarks to {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $bookmarks = BookmarkFactory::times(3)->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $notification = new BookmarksAddedToFolderNotification($bookmarks->all(), $folder, $collaborator);

        $user->notify($notification);

        $folder->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.folder.exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} added 3 bookmarks to {$folder->name->present()} folder.");
    }
}
