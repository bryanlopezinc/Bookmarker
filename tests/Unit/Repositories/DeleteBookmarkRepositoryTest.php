<?php

namespace Tests\Unit\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Favorite;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\Models\UserBookmarksCount;
use App\Models\UserFavoritesCount;
use App\Repositories\DeleteBookmarkRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Tests\TestCase;

class DeleteBookmarkRepositoryTest extends TestCase
{
    private DeleteBookmarkRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(DeleteBookmarkRepository::class);
    }

    public function testWillDecrementUserBookmarksCount(): void
    {
        $user = UserFactory::new()->create();
        $bookmarks = BookmarkFactory::new()->count(5)->for($user)->create();

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
        $user= UserFactory::new()->create();
        $bookmarkIDs = BookmarkFactory::new()->count(5)->for($user)->create()->pluck('id');

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
        $user = UserFactory::new()->create();
        $bookmarkIDs = BookmarkFactory::new()->count(2)->for($user)->create()->pluck('id');

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

    public function testWillDecrementUserFavoritesCount(): void
    {
        $user = UserFactory::new()->create();
        $bookmarkIDs = BookmarkFactory::new()->count(5)->for($user)->create()->pluck('id');

        Favorite::query()->create([
            'bookmark_id' => $bookmarkIDs->random(),
            'user_id' => $user->id,
        ]);

        UserFavoritesCount::create([
            'user_id' => $user->id,
            'count'   => 1,
        ]);

        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs)
        );

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserFavoritesCount::TYPE
        ]);
    }

    public function testWillNotDecrementUserFavoritesCounts(): void
    {
        $user = UserFactory::new()->create();
        $bookmarkIDs = BookmarkFactory::new()->count(5)->for($user)->create()->pluck('id');

        //Favorite last bookmark
        Favorite::query()->create([
            'bookmark_id' => $bookmarkIDs->last(),
            'user_id' => $user->id,
        ]);

        UserFavoritesCount::create([
            'user_id' => $user->id,
            'count'   => 1,
        ]);

        //delete all except last one which was added to favorites
        $this->repository->delete(
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs->take(4))
        );

        $this->assertDatabaseHas(UserFavoritesCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserFavoritesCount::TYPE
        ]);
    }
}
