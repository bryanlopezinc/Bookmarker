<?php

namespace Tests\Unit;

use App\Collections\BookmarksCollection;
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
        $isEnabled = HealthChecker::isEnabled();

        HealthChecker::enable();

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

        HealthChecker::enable($isEnabled);
    }

    public function testWillNotCheckBookmarksHealthifDisabled(): void
    {
        $isEnabled = HealthChecker::isEnabled();

        HealthChecker::enable(false);

        $checker = new HealthChecker(new BookmarksHealthRepository);

        /** @var array<Bookmark> */
        $bookmarks = BookmarkFactory::new()
            ->count(5)
            ->create()
            ->map(fn (Model $model) => BookmarkBuilder::fromModel($model)->build());

        $checker->ping(new BookmarksCollection($bookmarks));

        Http::assertNothingSent();

        HealthChecker::enable($isEnabled);
    }
}
