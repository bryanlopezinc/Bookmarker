<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\UserBookmarksFilters as Data;
use App\Models\Favourite;
use App\Repositories\TagRepository;
use App\Repositories\UserBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\BookmarkFactory;
use Database\Factories\SourceFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class UserBookmarksRepositoryTest extends TestCase
{
    private UserBookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(UserBookmarksRepository::class);
    }

    public function testWillFetchUserBookmarks(): void
    {
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id
        ]);

        foreach ($this->repository->fetch(new UserID($userId), Data::fromArray([])) as $bookmark) {
            $this->assertTrue($userId === $bookmark->ownerId->toInt());
            $this->assertFalse($bookmark->isUserFavourite);
        }
    }

    public function testWillReturnUserBookmarksFromAParticularSource(): void
    {
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id,
        ]);

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId,
            'source_id' => $sourceID = SourceFactory::new()->create()->id
        ]);

        $result = $this->repository->fetch(new UserID($userId), Data::fromArray([
            'siteId' => new ResourceID($sourceID)
        ]));

        $this->assertCount(5, $result);

        foreach ($result as $bookmark) {
            $this->assertEquals($bookmark->sourceID->toInt(), $sourceID);
        }
    }

    public function testWillReturnUserBookmarksWithAParticularTag(): void
    {
        $models = BookmarkFactory::new()->count(10)->create([
            'user_id' => $userId = UserFactory::new()->create()->id,
        ]);

        (new TagRepository)->attach(TagsCollection::make(['foobar']), $models[0]);

        $result = $this->repository->fetch(new UserID($userId), Data::fromArray([
            'tags' => ['foobar']
        ]));

        $this->assertCount(1, $result);

        $this->assertEquals($models[0]->id, $result[0]->id->toInt());
    }

    public function testWillSetIsUserFavourite(): void
    {
        $bookmarks = BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id
        ]);

        Favourite::query()->create([
            'bookmark_id' => $favouriteID = $bookmarks->first()->id,
            'user_id' => $userId
        ]);

        /** @var Bookmark */
        $bookmark = $this->repository->fetch(new UserID($userId), Data::fromArray([]))
            ->getCollection()
            ->filter(fn (Bookmark $bookmark) => $bookmark->id->toInt() === $favouriteID)
            ->sole();

        $this->assertTrue($bookmark->isUserFavourite);
    }
}
