<?php

namespace Tests\Unit\Repositories;

use App\HealthCheckResult;
use App\Models\BookmarkHealth;
use App\Repositories\BookmarksHealthRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use Illuminate\Http\Client\Response;
use Tests\TestCase;
use GuzzleHttp\Psr7\Response as Psr7Response;

class BookmarksHealthRepositoryTest extends TestCase
{
    public function testWillReturnOnlyBookmarkIdsNotCheckedInSixDays(): void
    {
        /** @var array<int> */
        $ids = BookmarkFactory::new()->count(4)->create()->pluck('id')->all();

        [$first, $second, $third] = $ids;

        BookmarkHealthFactory::new()->create(['bookmark_id' => $first]); //Recently checked
        BookmarkHealthFactory::new()->checkedDaysAgo(6)->create(['bookmark_id' => $second]);
        BookmarkHealthFactory::new()->checkedDaysAgo(7)->create(['bookmark_id' => $third]);

        $result = (new BookmarksHealthRepository())->whereNotRecentlyChecked($ids);

        $this->assertCount(3, $result);
        $this->assertContains($second, $result);
        $this->assertContains($third, $result);
    }

    public function testWillReturnBookmarkIdsThatHasNeverBeenChecked(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        $result = (new BookmarksHealthRepository())->whereNotRecentlyChecked([$bookmark->id]);

        $this->assertCount(1, $result);
        $this->assertContains($bookmark->id, $result);
    }

    public function testUpdateRecords(): void
    {
        /** @var array<int> */
        $ids = BookmarkFactory::new()->count(3)->create()->pluck('id')->all();

        [$first, $second, $third] = $ids;

        $time = now()->toDateString();

        //first bookmarkID was healthy.
        BookmarkHealthFactory::new()->create(['bookmark_id' => $first, 'last_checked' => now()->subWeek()]);

        (new BookmarksHealthRepository())->update([
            new HealthCheckResult($first, new Response(new Psr7Response(404))),
            new HealthCheckResult($second, new Response(new Psr7Response())),
            new HealthCheckResult($third, new Response(new Psr7Response())),
        ]);

        /** @var array<BookmarkHealth> */
        $records = BookmarkHealth::query()
            ->whereIn('bookmark_id', [$first, $second, $third])
            ->get(['status_code', 'last_checked']);

        $this->assertEquals($records[0]->getAttributes(), [
            'status_code'  => 404,
            'last_checked' => $time
        ]);

        $this->assertEquals($records[1]->getAttributes(), [
            'status_code'  => 200,
            'last_checked' => $time
        ]);

        $this->assertEquals($records[2]->getAttributes(), [
            'status_code'  => 200,
            'last_checked' => $time
        ]);
    }
}
