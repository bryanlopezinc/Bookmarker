<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Repositories\BookmarkRepository;
use App\Repositories\FetchNotificationResourcesRepository;
use App\Repositories\UserRepository;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;

class FetchNotificationResourcesRepositoryTest extends TestCase
{
    public function testWillNotRequestDuplicateBookmarks(): void
    {
        $notifications = Collection::times(5, function () {
            return new DatabaseNotification([
                'data' => [
                    'bookmarks' => [10, 20, 30, 40, 50]
                ]
            ]);
        });

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('findManyById')
                ->once()
                ->withArgs(function (ResourceIDsCollection $bookmarkIDs) {
                    $this->assertCount(5, $bookmarkIDs);
                    $this->assertEquals([10, 20, 30, 40, 50], $bookmarkIDs->asIntegers()->all());
                    return true;
                })
                ->andReturn(collect());
        });

        new FetchNotificationResourcesRepository($notifications);
    }

    public function testWillNotRequestDuplicateUser_Ids(): void
    {
        $notifications = [
            new DatabaseNotification([
                'data' => ['added_by' => 10]
            ]),
            new DatabaseNotification([
                'data' => ['removed_by' => 10]
            ]),
            new DatabaseNotification([
                'data' => ['new_collaborator_id' => 20]
            ]),
            new DatabaseNotification([
                'data' => ['updated' => 10]
            ]),
        ];

        $this->mock(UserRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('findManyByIDs')
                ->once()
                ->withArgs(function (ResourceIDsCollection $userIDs) {
                    $this->assertCount(2, $userIDs);
                    $this->assertEquals([10, 20], $userIDs->asIntegers()->values()->all());
                    return true;
                })
                ->andReturn(collect());
        });

        new FetchNotificationResourcesRepository(collect($notifications));
    }
}
