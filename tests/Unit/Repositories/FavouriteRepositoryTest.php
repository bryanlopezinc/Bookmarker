<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Repositories\FavouriteRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\UserFactory;
use Tests\TestCase;

class FavouriteRepositoryTest extends TestCase
{
    public function testWillThrowDuplicateEntryExceptionWhenBookmarkExists(): void
    {
        $this->expectExceptionCode(23000);

        $repository = new FavouriteRepository;
        $user = UserFactory::new()->create();

        $repository->create(new ResourceID(5), new UserID($user->id));
        $repository->create(new ResourceID(5), new UserID($user->id));
    }

    public function testWillThrowDuplicateEntryExceptionWhenBookmarksExists(): void
    {
        $this->expectExceptionCode(23000);

        $user = UserFactory::new()->create();
        $repository = new FavouriteRepository;

        $repository->createMany(ResourceIDsCollection::fromNativeTypes([1, 2, 3, 4, 1]), new UserID($user->id));
    }
}
