<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\HealthCheckResult;
use App\Models\Bookmark;
use App\Models\BookmarkHealth;
use App\Repositories\BookmarksHealthRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use Illuminate\Http\Client\Response;
use Tests\TestCase;
use GuzzleHttp\Psr7\Response as Psr7Response;

class BookmarksHealthRepositoryTest extends TestCase
{
    public function testWillReturnOnlyBookmarkIdsNotCheckedInSixDays(): void
    {
        /** @var array<ResourceID> */
        $ids = BookmarkFactory::new()->count(4)->create()->map(fn (Bookmark $model) => new ResourceID($model->id))->all();

        [$first, $second, $third] = $ids;

        BookmarkHealthFactory::new()->create(['bookmark_id' => $first->value()]); //Recently checked
        BookmarkHealthFactory::new()->checkedDaysAgo(6)->create(['bookmark_id' => $second->value()]);
        BookmarkHealthFactory::new()->checkedDaysAgo(7)->create(['bookmark_id' => $third->value()]);

        $result = (new BookmarksHealthRepository)->whereNotRecentlyChecked(new ResourceIDsCollection($ids));

        $this->assertCount(3, $result);
        $this->assertContains($second->value(), $result->asIntegers()->all());
        $this->assertContains($third->value(), $result->asIntegers()->all());
    }

    public function testWillReturnBookmarkIdsThatHasNeverBeenChecked(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        $result = (new BookmarksHealthRepository)->whereNotRecentlyChecked((new ResourceID($bookmark->id))->toCollection());

        $this->assertCount(1, $result);
        $this->assertContains($bookmark->id, $result->asIntegers()->all());
    }

    public function testUpdateRecords(): void
    {
        /** @var array<ResourceID> */
        $ids = BookmarkFactory::new()->count(3)->create()->map(fn (Bookmark $model) => new ResourceID($model->id))->all();

        [$first, $second, $third] = $ids;

        $time = now()->toDateString();

        //first bookmarkID was healthy.
        BookmarkHealthFactory::new()->create(['bookmark_id' => $first->value()]);

        (new BookmarksHealthRepository)->update([
            new HealthCheckResult($first, new Response(new Psr7Response(404))),
            new HealthCheckResult($second, new Response(new Psr7Response())),
            new HealthCheckResult($third, new Response(new Psr7Response())),
        ]);

        $this->assertDatabaseHas(BookmarkHealth::class, [
            'bookmark_id' => $first->value(),
            'is_healthy' => false,
            'last_checked' => $time
        ]);

        $this->assertDatabaseHas(BookmarkHealth::class, [
            'bookmark_id' => $second->value(),
            'is_healthy' => true,
            'last_checked' => $time,
        ]);

        $this->assertDatabaseHas(BookmarkHealth::class, [
            'bookmark_id' => $third->value(),
            'is_healthy' => true,
            'last_checked' => $time,
        ]);
    }
}
