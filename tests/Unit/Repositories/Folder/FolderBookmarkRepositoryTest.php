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
}
