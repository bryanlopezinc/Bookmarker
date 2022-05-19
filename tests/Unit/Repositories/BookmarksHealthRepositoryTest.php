<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Bookmark;
use App\Models\BookmarkHealth;
use App\Repositories\BookmarksHealthRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use Tests\TestCase;

class BookmarksHealthRepositoryTest extends TestCase
{
    public function testWillReturnOnlyBookmarkIdsNotCheckedInSixDays(): void
    {
        /** @var array<ResourceID> */
        $ids = BookmarkFactory::new()->count(3)->create()->map(fn (Bookmark $model) => new ResourceID($model->id))->all();

        BookmarkHealthFactory::new()->create([
            'bookmark_id' => $ids[0]->toInt(),
        ]);

        BookmarkHealthFactory::new()->checkedDaysAgo(6)->create([
            'bookmark_id' => $ids[1]->toInt(),
        ]);

        BookmarkHealthFactory::new()->checkedDaysAgo(7)->create([
            'bookmark_id' => $ids[2]->toInt(),
        ]);

        $result = (new BookmarksHealthRepository)->whereNotRecentlyChecked(new ResourceIDsCollection($ids));

        $this->assertCount(2, $result);
        $this->assertContains($ids[1]->toInt(), $result->asIntegers()->all());
        $this->assertContains($ids[2]->toInt(), $result->asIntegers()->all());
    }

    public function testWillReturnBookmarksIdsThatDontExists(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        BookmarkHealthFactory::new()->create([
            'bookmark_id' => $bookmark->id,
            'last_checked' => now()->subDays(7)
        ]);

        $result = (new BookmarksHealthRepository)->whereNotRecentlyChecked(
            ResourceIDsCollection::fromNativeTypes([$bookmark->id, $bookmark->id + 1])
        );

        $this->assertCount(2, $result);
        $this->assertContains($bookmark->id, $result->asIntegers()->all());
        $this->assertContains($bookmark->id + 1, $result->asIntegers()->all());
    }

    public function testWillupdateRecords(): void
    {
        /** @var array<ResourceID> */
        $ids = BookmarkFactory::new()->count(3)->create()->map(fn (Bookmark $model) => new ResourceID($model->id))->all();

        [$first, $second, $third] = $ids;

        $time = now()->toDateString();

        BookmarkHealthFactory::new()->create([
            'bookmark_id' => $first->toInt(),
        ]);

        (new BookmarksHealthRepository)->update([
            $first->toInt() => false,
            $second->toInt() => true,
            $third->toInt() => true,
        ]);

        $this->assertDatabaseHas(BookmarkHealth::class, [
            'bookmark_id' => $first->toInt(),
            'is_healthy' => false,
            'last_checked' => $time
        ]);

        $this->assertDatabaseHas(BookmarkHealth::class, [
            'bookmark_id' => $second->toInt(),
            'is_healthy' => true,
            'last_checked' => $time,
        ]);

        $this->assertDatabaseHas(BookmarkHealth::class, [
            'bookmark_id' => $third->toInt(),
            'is_healthy' => true,
            'last_checked' => $time,
        ]);
    }
}
