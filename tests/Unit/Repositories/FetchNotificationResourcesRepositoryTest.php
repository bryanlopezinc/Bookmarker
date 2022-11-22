<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection as IDs;
use App\DataTransferObjects\Builders\DatabaseNotificationBuilder as Builder;
use App\Repositories\BookmarkRepository;
use App\Repositories\FetchNotificationResourcesRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;
use App\Notifications\BookmarksAddedToFolderNotification as NewBookmarksNotification;
use App\Notifications\BookmarksRemovedFromFolderNotification as BookmarksRemovedNotification;
use App\Notifications\CollaboratorExitNotification;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

class FetchNotificationResourcesRepositoryTest extends TestCase
{
    public function testWillNotRequestDuplicateBookmarks(): void
    {
        $notifications = Collection::times(5, function () {
            return Builder::new()->data($this->newBookmarksNotificationData([10, 20, 30, 40, 50], 22, 33))->build();
        });

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('findManyById')
                ->once()
                ->withArgs(function (IDs $bookmarkIDs) {
                    $this->assertCount(5, $bookmarkIDs);
                    $this->assertEquals([10, 20, 30, 40, 50], $bookmarkIDs->asIntegers()->all());
                    return true;
                })
                ->andReturn(collect());
        });

        new FetchNotificationResourcesRepository($notifications);
    }

    private function newBookmarksNotificationData(array $bookmarks, int $folderID, int $collaboratorID): array
    {
        return (new NewBookmarksNotification(
            IDs::fromNativeTypes($bookmarks),
            new ResourceID($folderID),
            new UserID($collaboratorID)
        ))->toDatabase('');
    }

    private function bookmarksRemovedNotificationData(array $bookmarks, int $folderID, int $collaboratorID): array
    {
        return (new BookmarksRemovedNotification(
            IDs::fromNativeTypes($bookmarks),
            new ResourceID($folderID),
            new UserID($collaboratorID)
        ))->toDatabase('');
    }

    private function collaboratorExitNotificationData(int $folderID, int $collaboratorID): array
    {
        return (new CollaboratorExitNotification(
            new ResourceID($folderID),
            new UserID($collaboratorID)
        ))->toDatabase('');
    }

    public function testWillNotRequestDuplicateUser_Ids(): void
    {
        $notifications = [
            Builder::new()->data($this->newBookmarksNotificationData([10, 20, 30], 22, 33))->build(),
            Builder::new()->data($this->bookmarksRemovedNotificationData([10, 40, 50], 22, 34))->build(),
            Builder::new()->data($this->collaboratorExitNotificationData(32, 33))->build(),
        ];

        $this->mock(UserRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('findManyByIDs')
                ->once()
                ->withArgs(function (IDs $userIDs) {
                    $this->assertCount(2, $userIDs);
                    $this->assertEquals([33, 34], $userIDs->asIntegers()->values()->all());
                    return true;
                })
                ->andReturn(collect());
        });

        new FetchNotificationResourcesRepository(collect($notifications));
    }
}
