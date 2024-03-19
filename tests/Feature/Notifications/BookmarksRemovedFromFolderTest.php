<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;
use Database\Factories\FolderFactory;
use Database\Factories\BookmarkFactory;
use App\Notifications\BookmarksRemovedFromFolderNotification;
use PHPUnit\Framework\Attributes\Test;

class BookmarksRemovedFromFolderTest extends TestCase
{
    use MakesHttpRequest;

    public function testFetchNotifications(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification($collaboratorBookmarks->all(), $folder, $collaborator)
        );

        $collaborator->update(['first_name' => 'bryan', 'last_name' => 'wayne']);
        $folder->update(['name' => 'Apache problems']);

        $expectedDateTime = $user->notifications()->sole(['created_at'])->created_at;

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(7, 'data.0.attributes')
            ->assertJsonPath('data.0.type', 'BookmarksRemovedFromFolderNotification')
            ->assertJsonPath('data.0.attributes.collaborator_exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder_exists', true)
            ->assertJsonPath('data.0.attributes.message', 'Bryan Wayne removed 3 bookmarks from Apache Problems folder.')
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
    public function whenOneBookmarkWasRemoved(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification([$collaboratorBookmark->id], $folder, $collaborator)
        );

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed 1 bookmark from {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification($collaboratorBookmarks->all(), $folder, $collaborator)
        );

        $collaborator->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborator_exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed 3 bookmarks from {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create()->pluck('id');
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification($collaboratorBookmarks->all(), $folder, $collaborator)
        );

        $folder->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder_exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed 3 bookmarks from {$folder->name->present()} folder.");
    }
}
