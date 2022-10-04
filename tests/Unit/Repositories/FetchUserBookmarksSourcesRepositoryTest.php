<?php

namespace Tests\Unit\Repositories;

use App\PaginationData;
use Tests\TestCase;
use App\ValueObjects\UserID;
use Database\Factories\SourceFactory;
use Database\Factories\UserFactory;
use Database\Factories\BookmarkFactory;
use App\Repositories\FetchUserBookmarksSourcesRepository as Repository;

class FetchUserBookmarksSourcesRepositoryTest extends TestCase
{
    public function testWillFetchUserBookmarksSources(): void
    {
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id,
            'source_id' => SourceFactory::new()->create()->id
        ]);

        BookmarkFactory::new()->count(4)->create(['user_id' => $userId]);
        BookmarkFactory::new()->count(5)->create();

        $result = (new Repository)->get(new UserID($userId), new PaginationData());

        $this->assertCount(5, $result);
    }
}
