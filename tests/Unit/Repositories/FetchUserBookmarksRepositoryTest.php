<?php

namespace Tests\Unit\Repositories;

use App\Collections\TagsCollection;
use App\DataTransferObjects\FetchUserBookmarksRequestData as Data;
use App\Repositories\BookmarksRepository;
use App\Repositories\TagsRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\Tag;
use App\ValueObjects\UserID;
use Database\Factories\BookmarkFactory;
use Database\Factories\SiteFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class FetchUserBookmarksRepositoryTest extends TestCase
{
    private BookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(BookmarksRepository::class);
    }

    public function testWillFetchUserBookmarks(): void
    {
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id
        ]);

        foreach ($this->repository->userBookmarks(Data::fromArray(['userId' => new UserID($userId)])) as $bookmark) {
            $this->assertTrue($userId === $bookmark->ownerId->toInt());
        }
    }

    public function testWillReturnUserBookmarksFromAParticularSite(): void
    {
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id,
        ]);

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId,
            'site_id' => $siteId = SiteFactory::new()->create()->id
        ]);

        $result = $this->repository->userBookmarks(Data::fromArray([
            'userId' => new UserID($userId),
            'siteId' => new ResourceID($siteId)
        ]));

        $this->assertCount(5, $result);

        foreach ($result as $bookmark) {
            $this->assertEquals($bookmark->webPagesiteId->toInt(), $siteId);
        }
    }

    public function testWillReturnUserBookmarksWithAParticularTag(): void
    {
        $models = BookmarkFactory::new()->count(10)->create([
            'user_id' => $userId = UserFactory::new()->create()->id,
        ]);

        (new TagsRepository)->attach(TagsCollection::createFromStrings(['foobar']), $models[0]);

        $result = $this->repository->userBookmarks(Data::fromArray([
            'userId' => new UserID($userId),
            'tag' => new Tag('foobar')
        ]));

        $this->assertCount(1, $result);

        $this->assertEquals($models[0]->id, $result[0]->id->toInt());
    }
}
