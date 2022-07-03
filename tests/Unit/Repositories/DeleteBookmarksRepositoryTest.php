<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Favourite;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\Models\UserBookmarksCount;
use App\Models\UserFavouritesCount;
use App\Repositories\DeleteBookmarksRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
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

    public function testWillDecrementUserBookmarksCount(): void
    {
        $user = UserFactory::new()->create();

        $bookmarks = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ]);

        UserBookmarksCount::create([
            'user_id' => $user->id,
            'count'   => 5,
        ]);

        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarks->pluck('id'))
        );

        $this->assertDatabaseHas(UserBookmarksCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserBookmarksCount::TYPE
        ]);
    }

    public function testWillDeleteBookmarkFromFolderWhenBookmarkExistsInFolder(): void
    {
        $userID = UserFactory::new()->create()->id;

        $bookmarkIDs = BookmarkFactory::new()->count(5)->create([
            'user_id' => $userID
        ])->pluck('id');

        FolderBookmark::create([
            'bookmark_id' => $bookmarkID = $bookmarkIDs->first(),
            'folder_id' => $folderID = FolderFactory::new()->create()->id,
            'is_public' => true
        ]);

        FolderBookmarksCount::create([
            'folder_id' => $folderID,
            'count' => 1
        ]);

        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs)
        );

        $this->assertDatabaseMissing(FolderBookmark::class, [
            'bookmark_id' => $bookmarkID,
            'folder_id' => $folderID
        ]);

        //Assert folder bookmarks count was decremented
        $this->assertDatabaseHas(FolderBookmarksCount::class, [
            'count' => 0,
            'folder_id' => $folderID
        ]);
    }

    public function testWillNotDeleteBookmarkFromFolderWhenBookmarkDoesNotExistsInFolder(): void
    {
        $userID = UserFactory::new()->create()->id;

        $bookmarkIDs = BookmarkFactory::new()->count(2)->create([
            'user_id' => $userID
        ])->pluck('id');

        FolderBookmark::create([
            'bookmark_id' => $bookmarkID = BookmarkFactory::new()->create()->id,
            'folder_id' => $folderID = FolderFactory::new()->create()->id,
            'is_public' => true
        ]);

        FolderBookmarksCount::create([
            'folder_id' => $folderID,
            'count' => 1
        ]);

        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs)
        );

        $this->assertDatabaseHas(FolderBookmark::class, [
            'bookmark_id' => $bookmarkID,
            'folder_id' => $folderID
        ]);

        //Assert folder bookmarks count was not decremented
        $this->assertDatabaseHas(FolderBookmarksCount::class, [
            'count' => 1,
            'folder_id' => $folderID
        ]);
    }

    public function testWillDecrementUserFavouritesCount(): void
    {
        $user = UserFactory::new()->create();

        $bookmarkIDs = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ])->pluck('id');

        Favourite::query()->create([
            'bookmark_id' => $bookmarkIDs->random(),
            'user_id' => $user->id,
        ]);

        UserFavouritesCount::create([
            'user_id' => $user->id,
            'count'   => 1,
        ]);

        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs)
        );

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testWillNotDecrementUserFavouritesCounts(): void
    {
        $user = UserFactory::new()->create();

        $bookmarkIDs = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ])->pluck('id');

        //favourite last bookmark
        Favourite::query()->create([
            'bookmark_id' => $bookmarkIDs->last(),
            'user_id' => $user->id,
        ]);

        UserFavouritesCount::create([
            'user_id' => $user->id,
            'count'   => 1,
        ]);

        //delete all except last one which was added to favourites
        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs->take(4))
        );

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserFavouritesCount::TYPE
        ]);
    }
}
