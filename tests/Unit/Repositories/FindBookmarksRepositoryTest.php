<?php

namespace Tests\Unit\Repositories;

use App\QueryColumns\BookmarkQueryColumns as BookmarkColumns;
use App\Repositories\FindBookmarksRepository;
use App\ValueObjects\ResourceId;
use Database\Factories\BookmarkFactory;
use Tests\TestCase;

class FindBookmarksRepositoryTest extends TestCase
{
    private FindBookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(FindBookmarksRepository::class);
    }

    public function testWillReturnOnlyUserId(): void
    {
        $model = BookmarkFactory::new()->create();

        $bookmark = $this->repository->findById(new ResourceId($model->id), BookmarkColumns::new()->userId());

        $bookmark->ownerId;
        $this->assertCount(1, $bookmark->toArray());
    }

    public function testWillReturnOnlyTags(): void
    {
        $model = BookmarkFactory::new()->create();

        $bookmark = $this->repository->findById(new ResourceId($model->id), BookmarkColumns::new()->tags());

        $bookmark->tags;
        $this->assertCount(1, $bookmark->toArray());
    }

    public function testWillReturnOnlySpecifiedAttributes(): void
    {
        $model = BookmarkFactory::new()->create();

        $bookmark = $this->repository->findById(new ResourceId($model->id), BookmarkColumns::new()->tags()->userId());

        $bookmark->ownerId;
        $bookmark->tags;
        $this->assertCount(2, $bookmark->toArray());
    }
}
