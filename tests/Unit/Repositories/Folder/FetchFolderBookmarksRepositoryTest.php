<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\Folder;

use App\Collections\ResourceIDsCollection;
use App\Models\FolderBookmark;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use Database\Factories\BookmarkFactory;
use Database\Factories\FolderFactory;
use Tests\TestCase;

class FetchFolderBookmarksRepositoryTest extends TestCase
{
    public function testContainsWillReturnTrue(): void
    {
        $repository = new FetchFolderBookmarksRepository;

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
        $repository = new FetchFolderBookmarksRepository;

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
        $repository = new FetchFolderBookmarksRepository;

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
        $repository = new FetchFolderBookmarksRepository;

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
