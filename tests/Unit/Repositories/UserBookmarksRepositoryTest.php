<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\UserBookmarksFilters as Data;
use App\Models\Favorite;
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
            $this->assertTrue($userId === $bookmark->ownerId->value());
            $this->assertFalse($bookmark->isUserFavorite);
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
            'source_id' => new ResourceID($sourceID)
        ]));

        $this->assertCount(5, $result);

        foreach ($result as $bookmark) {
            $this->assertEquals($bookmark->sourceID->value(), $sourceID);
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

        $this->assertEquals($models[0]->id, $result[0]->id->value());
    }

    public function testWillSetIsUserFavorite(): void
    {
        $bookmarks = BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id
        ]);

        Favorite::query()->create([
            'bookmark_id' => $favoriteID = $bookmarks->first()->id,
            'user_id' => $userId
        ]);

        $this->repository->fetch(new UserID($userId), Data::fromArray([]))
            ->getCollection()
            ->each(function (Bookmark $bookmark) use ($favoriteID) {
                if ($bookmark->id->value() === $favoriteID) {
                    $this->assertTrue($bookmark->isUserFavorite);
                } else {
                    $this->assertFalse($bookmark->isUserFavorite);
                }
            });
    }
}
