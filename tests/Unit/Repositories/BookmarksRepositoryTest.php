<?php

namespace Tests\Unit\Repositories;

use App\QueryColumns\BookmarkQueryColumns as BookmarkColumns;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Tests\TestCase;

class BookmarksRepositoryTest extends TestCase
{
    private BookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(BookmarksRepository::class);
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
}
