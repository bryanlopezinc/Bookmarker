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
        BookmarkFactory::new()->count(5)->for($user = UserFactory::new()->create())->create([
            'source_id' => SourceFactory::new()->create()->id
        ]);

        BookmarkFactory::new()->count(4)->create(['user_id' => $user->id]);
        BookmarkFactory::new()->count(5)->create();

        $result = (new Repository)->get(new UserID($user->id), new PaginationData());

        $this->assertCount(5, $result);
    }
}
