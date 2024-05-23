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
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification($collaboratorBookmarks->all(), $folder, $collaborator)
        );

        $collaborator->update(['first_name' => 'bryan', 'last_name' => 'wayne']);
        $folder->update(['name' => 'Apache problems']);

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonPath('data.0.type', 'BookmarksRemovedFromFolderNotification')
            ->assertJsonPath('data.0.attributes.collaborator.exists', true)
            ->assertJsonPath('data.0.attributes.id', fn (string $id) => Str::isUuid($id))
            ->assertJsonPath('data.0.attributes.folder.exists', true)
            ->assertJsonPath('data.0.attributes.message', 'Bryan Wayne removed 3 bookmarks from Apache Problems folder.')
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
    public function whenOneBookmarkWasRemoved(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmark = BookmarkFactory::new()->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification([$collaboratorBookmark], $folder, $collaborator)
        );

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed 1 bookmark from {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenCollaboratorNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification($collaboratorBookmarks->all(), $folder, $collaborator)
        );

        $collaborator->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborator.exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed 3 bookmarks from {$folder->name->present()} folder.");
    }

    public function testWillReturnCorrectPayloadWhenFolderNoLongerExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $collaboratorBookmarks = BookmarkFactory::times(3)->for($collaborator)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $user->notify(
            new BookmarksRemovedFromFolderNotification($collaboratorBookmarks->all(), $folder, $collaborator)
        );

        $folder->delete();

        $this->loginUser($user);
        $this->fetchNotificationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.folder.exists', false)
            ->assertJsonPath('data.0.attributes.message', "{$collaborator->full_name->present()} removed 3 bookmarks from {$folder->name->present()} folder.");
    }
}
