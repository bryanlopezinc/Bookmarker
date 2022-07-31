<?php

namespace Tests\Unit;

use App\Collections\BookmarksCollection;
use App\Collections\ResourceIDsCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\HealthChecker;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\HealthCheckResult;
use Database\Factories\BookmarkFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckerTest extends TestCase
{
    public function testCheckBookmarksHealth(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();

        $bookmarks = BookmarkFactory::new()
            ->count(3)
            ->state(new Sequence(
                ['id' => 200],
                ['id' => 250],
                ['id' => 251],
            ))
            ->make()
            ->map(fn (Model $model) => BookmarkBuilder::fromModel($model)->build());

        Http::fakeSequence()
            ->push()
            ->push(status: 404)
            ->dontFailWhenEmpty();

        $repository->expects($this->once())
            ->method('whereNotRecentlyChecked')
            ->willReturn($bookmarks->map(fn (Bookmark $bookmark) => $bookmark->id)->pipeInto(ResourceIDsCollection::class));

        $repository->expects($this->once())
            ->method('update')
            ->with($this->callback(function (array $data) {
                /** @var HealthCheckResult[] $data */
                foreach ($data as $healthCheckResult) {
                    if ($healthCheckResult->bookmarkID->toInt() === 250) {
                        $this->assertEquals(404, $healthCheckResult->response->status());
                        continue;
                    }

                    $this->assertEquals(200, $healthCheckResult->response->status());
                }

                return true;
            }));

        $checker = new HealthChecker($repository);
        $checker->ping(new BookmarksCollection($bookmarks));

        Http::assertSentCount(3);
    }

    public function testWillNotMakeHttpRequestIfBookmarksCollectionIsEmpty(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();
        $repository->expects($this->never())->method('whereNotRecentlyChecked');

        $checker = new HealthChecker($repository);
        $checker->ping(new BookmarksCollection([]));
    }

    public function testWillNotUpdateDataIfAllBookmarksHaveBeenRecentlyChecked(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();

        $repository->expects($this->never())->method('update');
        $repository->expects($this->once())->method('whereNotRecentlyChecked')->willReturn(new ResourceIDsCollection([]));

        $checker = new HealthChecker($repository);

        $checker->ping(new BookmarksCollection([
            BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 550]))->build()
        ]));
    }

    public function testWillOnlyMakeRequestsIfBookmarkUrlIsHttp(): void
    {
        Http::fakeSequence()->dontFailWhenEmpty();

        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();

        $bookmarks = BookmarkFactory::new()
            ->count(3)
            ->state(new Sequence(
                ['id' => 40],
                ['id' => 42, 'url' => 'payto://iban/DE75512108001245126199?amount=EUR:200.0&message=hello'],
                ['id' => 43],
            ))
            ->make()
            ->map(fn (Model $model) => BookmarkBuilder::fromModel($model)->build());

        $repository->expects($this->once())
            ->method('whereNotRecentlyChecked')
            ->willReturn($bookmarks->map(fn (Bookmark $bookmark) => $bookmark->id)->pipeInto(ResourceIDsCollection::class));

        $repository->expects($this->once())
            ->method('update')
            ->with($this->callback(function (array $data) {
                /** @var HealthCheckResult[] $data */
                foreach ($data as $healthCheckResult) {
                    $this->assertNotEquals(42, $healthCheckResult->bookmarkID->toInt());
                }
                return true;
            }));

        (new HealthChecker($repository))->ping(new BookmarksCollection($bookmarks));

        Http::assertSentCount(2);
    }
}
