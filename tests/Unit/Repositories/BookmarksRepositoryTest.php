<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Bookmark;
use App\QueryColumns\BookmarkAttributes as BookmarkColumns;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use ReflectionProperty;
use Tests\TestCase;

class BookmarksRepositoryTest extends TestCase
{
    private FetchBookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(FetchBookmarksRepository::class);
    }

    public function testWillReturnOnlySpecifiedAttributes(): void
    {
        //assert will return all attributes when no attributes are requested
        $this->assertWillReturnOnlyAttributes('', function (Bookmark $bookmark) {
            $expected = collect((new \ReflectionClass($bookmark::class))->getProperties(ReflectionProperty::IS_PUBLIC))
                ->map(fn (ReflectionProperty $property) => $property->name)
                ->reject('isUserFavourite')
                ->sort()
                ->values()
                ->all();

            $this->assertEquals($expected, collect($bookmark->toArray())->keys()->sort()->values()->all());
        });

        $this->assertWillReturnOnlyAttributes('userId', function (Bookmark $bookmark) {
            $this->assertCount(1, $bookmark->toArray());
            $bookmark->ownerId; // will throw initialization exception if not retrived
        });

        $this->assertWillReturnOnlyAttributes('userId,id', function (Bookmark $bookmark) {
            $this->assertCount(2, $bookmark->toArray());
            $bookmark->ownerId;
            $bookmark->id;
        });

        $this->assertWillReturnOnlyAttributes('tags', function (Bookmark $bookmark) {
            $this->assertCount(1, $bookmark->toArray());
            $bookmark->tags;
        });

        $this->assertWillReturnOnlyAttributes('tags,userId', function (Bookmark $bookmark) {
            $this->assertCount(2, $bookmark->toArray());
            $bookmark->ownerId;
            $bookmark->tags;
        });
    }

    private function assertWillReturnOnlyAttributes(string $attributes, \Closure $assertion): void
    {
        $bookmarkID = new ResourceID(BookmarkFactory::new()->create()->id);

        $bookmark = $this->repository->findById($bookmarkID, BookmarkColumns::only($attributes));

        $assertion($bookmark);
    }

    public function test_is_healthy_attribute_will_return_true_when_bookmark_id_does_not_exists_in_bookmarks_health_table(): void
    {
        $bookmark = $this->repository->findById(new ResourceID(BookmarkFactory::new()->create()->id));

        $this->assertTrue($bookmark->isHealthy);
    }

    public function test_is_healthy_attribute_will_return_true_when_bookmark_id_exists(): void
    {
        $model = BookmarkFactory::new()->create();

        BookmarkHealthFactory::new()->create([
            'bookmark_id' => $model->id,
        ]);

        $bookmark = $this->repository->findById(new ResourceID($model->id));

        $this->assertTrue($bookmark->isHealthy);
    }

    public function test_is_healthy_attribute_will_return_false_when_bookmark_id_exists(): void
    {
        $model = BookmarkFactory::new()->create();

        BookmarkHealthFactory::new()->unHealthy()->create([
            'bookmark_id' => $model->id,
        ]);

        $bookmark = $this->repository->findById(new ResourceID($model->id));

        $this->assertFalse($bookmark->isHealthy);
    }
}
