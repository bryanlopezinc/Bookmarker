<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Bookmark;
use App\Models\BookmarkHealth;
use App\Repositories\BookmarksHealthRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Tests\TestCase;

class BookmarksHealthRepositoryTest extends TestCase
{
    public function testWillReturnOnlyBookmarkIdsNotCheckedInSixDays(): void
    {
        /** @var array<ResourceID> */
        $ids = BookmarkFactory::new()->count(3)->create()->map(fn (Bookmark $model) => new ResourceID($model->id))->all();

        BookmarkHealth::insert([
            [
                'bookmark_id' => $ids[0]->toInt(),
                'is_healthy' => true,
                'last_checked' => now()->yesterday()
            ],
            [
                'bookmark_id' => $ids[1]->toInt(),
                'is_healthy' => true,
                'last_checked' => now()->subDays(6)
            ],
            [
                'bookmark_id' => $ids[2]->toInt(),
                'is_healthy' => true,
                'last_checked' => now()->subDays(7)
            ]
        ]);

        $result = (new BookmarksHealthRepository)->whereNotRecentlyChecked(new ResourceIDsCollection($ids));

        $this->assertCount(2, $result);
        $this->assertContains($ids[1]->toInt(), $result->asIntegers()->all());
        $this->assertContains($ids[2]->toInt(), $result->asIntegers()->all());
    }

    public function testWillReturnBookmarksIdsThatDontExists(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        BookmarkHealth::create([
            'bookmark_id' => $bookmark->id,
            'is_healthy' => true,
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

        BookmarkHealth::query()->create([
            'bookmark_id' => $first->toInt(),
            'is_healthy' => true,
            'last_checked' => now(),
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
