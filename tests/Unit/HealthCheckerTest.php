<?php

namespace Tests\Unit;

use App\Collections\BookmarksCollection;
use App\Collections\ResourceIDsCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\HealthChecker;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\Models\BookmarkHealth;
use App\Repositories\BookmarksHealthRepository;
use Database\Factories\BookmarkFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckerTest extends TestCase
{
    public function testWillCheckBookmarks(): void
    {
        $checker = new HealthChecker(new BookmarksHealthRepository);

        /** @var array<Bookmark> */
        $bookmarks = BookmarkFactory::new()
            ->count(5)
            ->create()
            ->map(fn (Model $model) => BookmarkBuilder::fromModel($model)->build());

        $callback = [];

        collect($bookmarks)->each(function (Bookmark $bookmark, int $key) use (&$callback) {
            $callback[$bookmark->linkToWebPage->value] = $key === 2 ? Http::response(status: 404) : Http::response();
        });

        Http::fake($callback);

        $checker->ping(new BookmarksCollection($bookmarks));

        foreach ($bookmarks as $key => $bookmark) {
            $this->assertDatabaseHas(BookmarkHealth::class, [
                'bookmark_id' => $bookmark->id->toInt(),
                'is_healthy' => $key === 2 ? false : true,
            ]);
        }

        Http::assertSentCount(5);
    }

    public function testWillNotMakeHttpRequestWhenBookmarksCollectionIsEmpty(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();

        $repository->expects($this->never())->method('whereNotRecentlyChecked');

        $checker = new HealthChecker($repository);

        $checker->ping(new BookmarksCollection([]));
    }

    public function testWillNotUpdateDataWhenAllBookmarksHaveBeenRecentlyChecked(): void
    {
        $repository = $this->getMockBuilder(BookmarksHealthRepositoryInterface::class)->getMock();

        $repository->expects($this->never())->method('update');
        $repository->expects($this->once())->method('whereNotRecentlyChecked')->willReturn(new ResourceIDsCollection([]));

        $checker = new HealthChecker($repository);

        $checker->ping(new BookmarksCollection([
            BookmarkBuilder::fromModel(BookmarkFactory::new()->create())->build()
        ]));
    }
}
