<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\Folder;

use App\Collections\ResourceIDsCollection;
use App\Models\FolderBookmark;
use App\Repositories\Folder\FolderBookmarkRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Tests\TestCase;

class FolderBookmarkRepositoryTest extends TestCase
{
    public function testFolderCannotHaveDuplicateBookmarks(): void
    {
        $this->expectExceptionCode(23000);

        $repository = new FolderBookmarkRepository;
        $bookmarkIDs = BookmarkFactory::new()->count(5)->create()->pluck('id');
        $folder = FolderFactory::new()->create();

        $repository->add(
            new ResourceID($folder->id),
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs->add($bookmarkIDs->random())),
            new ResourceIDsCollection([])
        );
    }

    public function testWillMakeBookmarksHidden(): void
    {
        $repository = new FolderBookmarkRepository;
        $bookmarkIDs = BookmarkFactory::new()->count(5)->create()->pluck('id');
        $folder = FolderFactory::new()->create();

        $repository->add(
            new ResourceID($folder->id),
            ResourceIDsCollection::fromNativeTypes($bookmarkIDs),
            ResourceIDsCollection::fromNativeTypes([$bookmarkIDs->last()])
        );

        $this->assertDatabaseHas(FolderBookmark::class, [
            'bookmark_id' => $bookmarkIDs->last(),
            'folder_id' => $folder->id,
            'is_public' => false
        ]);

        $bookmarkIDs->pop();

        $bookmarkIDs->each(fn (int $bookmarkID) => $this->assertDatabaseHas(FolderBookmark::class, [
            'bookmark_id' => $bookmarkID,
            'folder_id' => $folder->id,
            'is_public' => true
        ]));
    }

    public function testContainsWillReturnTrue(): void
    {
        $repository = new FolderBookmarkRepository;

        FolderBookmark::query()->create([
            'bookmark_id' => $bookmarkID = BookmarkFactory::new()->create()->id,
            'folder_id' => $folderID = FolderFactory::new()->create()->id,
            'is_public' => false
        ]);

        $this->assertTrue($repository->contains(
            ResourceIDsCollection::fromNativeTypes([$bookmarkID, BookmarkFactory::new()->create()->id]),
            new ResourceID($folderID)
        ));
    }

    public function testContainsWillReturnFalse(): void
    {
        $repository = new FolderBookmarkRepository;

        FolderBookmark::query()->create([
            'bookmark_id' => BookmarkFactory::new()->create()->id,
            'folder_id' => $folderID = FolderFactory::new()->create()->id,
            'is_public' => false
        ]);

        $this->assertFalse($repository->contains(
            new ResourceIDsCollection([]),
            new ResourceID($folderID)
        ));

        $this->assertFalse($repository->contains(
            ResourceIDsCollection::fromNativeTypes([BookmarkFactory::new()->create()->id]),
            new ResourceID($folderID)
        ));
    }

    public function testContainsAllWillReturnTrue(): void
    {
        $repository = new FolderBookmarkRepository;

        FolderBookmark::query()->create([
            'bookmark_id' => $bookmarkID = BookmarkFactory::new()->create()->id,
            'folder_id' => $folderID = FolderFactory::new()->create()->id,
            'is_public' => false
        ]);

        $this->assertTrue($repository->containsAll(
            ResourceIDsCollection::fromNativeTypes([$bookmarkID]),
            new ResourceID($folderID)
        ));
    }

    public function testContainsAllWillReturnFalse(): void
    {
        $repository = new FolderBookmarkRepository;

        FolderBookmark::query()->create([
            'bookmark_id' => $bookmarkID = BookmarkFactory::new()->create()->id,
            'folder_id' => $folderID = FolderFactory::new()->create()->id,
            'is_public' => false
        ]);

        $this->assertFalse($repository->containsAll(
            new ResourceIDsCollection([]),
            new ResourceID($folderID)
        ));

        $this->assertFalse($repository->containsAll(
            ResourceIDsCollection::fromNativeTypes([BookmarkFactory::new()->create()->id]),
            new ResourceID($folderID)
        ));

        $this->assertFalse($repository->containsAll(
            ResourceIDsCollection::fromNativeTypes([$bookmarkID, BookmarkFactory::new()->create()->id]),
            new ResourceID($folderID)
        ));
    }
}
