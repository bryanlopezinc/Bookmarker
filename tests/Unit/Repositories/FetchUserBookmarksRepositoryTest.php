<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\UserBookmarksFilters as Data;
use App\Models\Favorite;
use App\Repositories\TagRepository;
use App\Repositories\FetchUserBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\BookmarkFactory;
use Database\Factories\SourceFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class FetchUserBookmarksRepositoryTest extends TestCase
{
    private FetchUserBookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(FetchUserBookmarksRepository::class);
    }

    public function testWillFetchUserBookmarks(): void
    {
        BookmarkFactory::new()->count(5)->for($user = UserFactory::new()->create())->create();

        foreach ($this->repository->fetch(new UserID($user->id), Data::fromArray([])) as $bookmark) {
            $this->assertTrue($user->id === $bookmark->ownerId->value());
            $this->assertFalse($bookmark->isUserFavorite);
        }
    }

    public function testWillReturnUserBookmarksFromAParticularSource(): void
    {
        BookmarkFactory::new()->count(5)->for($user = UserFactory::new()->create())->create();

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id,
            'source_id' => $sourceID = SourceFactory::new()->create()->id
        ]);

        $result = $this->repository->fetch(new UserID($user->id), Data::fromArray([
            'source_id' => new ResourceID($sourceID)
        ]));

        $this->assertCount(5, $result);

        foreach ($result as $bookmark) {
            $this->assertEquals($bookmark->sourceID->value(), $sourceID);
        }
    }

    public function testWillReturnUserBookmarksWithAParticularTag(): void
    {
        $models = BookmarkFactory::new()->count(10)->for($user = UserFactory::new()->create())->create();

        (new TagRepository)->attach(TagsCollection::make(['foobar']), $models[0]);

        $result = $this->repository->fetch(new UserID($user->id), Data::fromArray([
            'tags' => ['foobar']
        ]));

        $this->assertCount(1, $result);

        $this->assertEquals($models[0]->id, $result[0]->id->value());
    }

    public function testWillSetIsUserFavorite(): void
    {
        $bookmarks = BookmarkFactory::new()->count(5)->for($user = UserFactory::new()->create())->create();

        Favorite::query()->create([
            'bookmark_id' => $favoriteID = $bookmarks->first()->id,
            'user_id' => $user->id
        ]);

        $this->repository->fetch(new UserID($user->id), Data::fromArray([]))
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
