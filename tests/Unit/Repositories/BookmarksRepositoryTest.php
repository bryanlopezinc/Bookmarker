<?php

namespace Tests\Unit\Repositories;

use App\DataTransferObjects\Bookmark;
use App\QueryColumns\BookmarkQueryColumns as BookmarkColumns;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use ReflectionProperty;
use Tests\TestCase;

class BookmarksRepositoryTest extends TestCase
{
    private BookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(BookmarksRepository::class);
    }

    public function testWillReturnAllAttributesWhenNoColumnsAreRequested(): void
    {
        $bookmark = $this->repository->findById(new ResourceID(BookmarkFactory::new()->create()->id));

        $expected = collect((new \ReflectionClass(Bookmark::class))->getProperties(ReflectionProperty::IS_PUBLIC))
            ->map(fn (ReflectionProperty $property) => $property->name)
            ->reject('isUserFavourite')
            ->sort()
            ->values()
            ->all();

        $this->assertEquals($expected, collect($bookmark->toArray())->keys()->sort()->values()->all());
    }

    public function testWillReturnOnlyUserId(): void
    {
        $model = BookmarkFactory::new()->create();

        $bookmark = $this->repository->findById(new ResourceID($model->id), BookmarkColumns::new()->userId());

        $bookmark->ownerId; // will throw initialization exception if not retrived
        $this->assertCount(1, $bookmark->toArray());
    }

    public function testWillReturnOnlyTags(): void
    {
        $model = BookmarkFactory::new()->create();

        $bookmark = $this->repository->findById(new ResourceID($model->id), BookmarkColumns::new()->tags());

        $bookmark->tags;
        $this->assertCount(1, $bookmark->toArray());
    }

    public function testWillReturnOnlySpecifiedAttributes(): void
    {
        $model = BookmarkFactory::new()->create();

        $bookmark = $this->repository->findById(new ResourceID($model->id), BookmarkColumns::new()->tags()->userId());

        $bookmark->ownerId;
        $bookmark->tags;
        $this->assertCount(2, $bookmark->toArray());
    }

    public function test_is_dead_link_attribute_will_return_false_when_bookmark_Id_does_not_exists(): void
    {
        $bookmark = $this->repository->findById(new ResourceID(BookmarkFactory::new()->create()->id));

        $this->assertFalse($bookmark->isDeadLink);
    }

    public function test_is_dead_link_attribute_will_return_true_when_bookmark_id_exists(): void
    {
        $model = BookmarkFactory::new()->create();

        BookmarkHealthFactory::new()->create([
            'bookmark_id' => $model->id,
        ]);

        $bookmark = $this->repository->findById(new ResourceID($model->id));

        $this->assertTrue($bookmark->isDeadLink);
    }

    public function test_is_dead_link_attribute_will_return_false_when_bookmark_id_exists(): void
    {
        $model = BookmarkFactory::new()->create();

        BookmarkHealthFactory::new()->unHealthy()->create([
            'bookmark_id' => $model->id,
        ]);

        $bookmark = $this->repository->findById(new ResourceID($model->id));

        $this->assertFalse($bookmark->isDeadLink);
    }
}
