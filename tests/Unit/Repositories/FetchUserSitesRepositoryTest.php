<?php

namespace Tests\Unit\Repositories;

use App\PaginationData;
use Tests\TestCase;
use App\ValueObjects\UserID;
use Database\Factories\SiteFactory;
use Database\Factories\UserFactory;
use Database\Factories\BookmarkFactory;
use App\Repositories\FetchUserSitesRepository;

class FetchUserSitesRepositoryTest extends TestCase
{
    public function testWillFetchUserSites(): void
    {
        BookmarkFactory::new()->count(5)->create([
            'user_id' => $userId = UserFactory::new()->create()->id,
            'site_id' => SiteFactory::new()->create()->id
        ]);

        BookmarkFactory::new()->count(4)->create(['user_id' => $userId]);
        BookmarkFactory::new()->count(5)->create();

        $result = (new FetchUserSitesRepository)->get(new UserID($userId), new PaginationData());

        $this->assertCount(5, $result);
    }
}
