<?php

namespace Tests\Unit;

use App\Contracts\BookmarksHealthRepositoryInterface;
use App\HealthChecker;
use App\Models\Bookmark as Model;
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
            ->make();

        Http::fakeSequence()
            ->push()
            ->push(status: 404)
            ->dontFailWhenEmpty();

        $repository->expects($this->once())
            ->method('whereNotRecentlyChecked')
            ->willReturn($bookmarks->map(fn (Model $bookmark) => $bookmark->id)->all());

        $repository->expects($this->once())
            ->method('update')
            ->with($this->callback(function (array $data) {
                /** @var HealthCheckResult[] $data */
                foreach ($data as $healthCheckResult) {
                    if ($healthCheckResult->bookmarkID === 250) {
                        $this->assertEquals(404, $healthCheckResult->response->status());
                        continue;
                    }

                    $this->assertEquals(200, $healthCheckResult->response->status());
                }

                return true;
            }));

        $checker = new HealthChecker($repository);
        $checker->ping($bookmarks);

        Http::assertSentCount(3);
    }

    public function testWillNotMakeHttpRequestIfBookmarksCollectionIsEmpty(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();
        $repository->expects($this->never())->method('whereNotRecentlyChecked');

        $checker = new HealthChecker($repository);
        $checker->ping([]);
    }

    public function testWillNotUpdateDataIfAllBookmarksHaveBeenRecentlyChecked(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();

        $repository->expects($this->never())->method('update');
        $repository->expects($this->once())->method('whereNotRecentlyChecked')->willReturn([]);

        $checker = new HealthChecker($repository);

        $checker->ping([BookmarkFactory::new()->make(['id' => 550])]);
    }
}
