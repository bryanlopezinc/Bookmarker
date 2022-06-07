<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Favourite;
use App\Models\UserBookmarksCount;
use App\Models\UserFavouritesCount;
use App\Repositories\DeleteBookmarksRepository;
use App\ValueObjects\UserID;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class DeleteBookmarksRepositoryTest extends TestCase
{
    private DeleteBookmarksRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DeleteBookmarksRepository::class);
    }

    public function testWillDecrementCountsWhenBookmarksAreDeleted(): void
    {
        $user = UserFactory::new()->create();

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        Favourite::query()->create([
            'bookmark_id' => $bookmarks->random()->id,
            'user_id' => $user->id,
        ]);

        UserFavouritesCount::create([
            'user_id' => $user->id,
            'count'   => 1,
        ]);

        UserBookmarksCount::create([
            'user_id' => $user->id,
            'count'   => 10,
        ]);

        $this->repository->deleteManyFor(
            new UserID($user->id),
            ResourceIDsCollection::fromNativeTypes($bookmarks->pluck('id'))
        );

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserFavouritesCount::TYPE
        ]);

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserBookmarksCount::TYPE
        ]);
    }

    public function testWillNotDecrementFavouritesCounts(): void
    {
        $user = UserFactory::new()->create();

        $bookmarks = BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        //favourite last bookmark
        Favourite::query()->create([
            'bookmark_id' => $bookmarks->last()->id,
            'user_id' => $user->id,
        ]);

        UserFavouritesCount::create([
            'user_id' => $user->id,
            'count'   => 1,
        ]);

        UserBookmarksCount::create([
            'user_id' => $user->id,
            'count'   => 10,
        ]);

        //delete all except last one which was added to favourites
        $this->repository->deleteManyFor(
            new UserID($user->id),
            ResourceIDsCollection::fromNativeTypes($bookmarks->pluck('id')->take(9))
        );

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserFavouritesCount::TYPE
        ]);

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserBookmarksCount::TYPE
        ]);
    }
}
