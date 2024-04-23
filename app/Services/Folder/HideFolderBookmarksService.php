<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\BookmarkPublicIdsCollection;
use App\Enums\FolderBookmarkVisibility;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class HideFolderBookmarksService
{
    public function hide(array $bookmarkIDs, FolderPublicId $folderID): void
    {
        $folder = Folder::query()
            ->withCasts(['bookmarks' => 'collection'])
            ->withCasts(['folderBookmarks' => 'collection'])
            ->select([
                'id',
                'user_id',
                'bookmarks' => Bookmark::query()
                    ->selectRaw("JSON_ARRAYAGG(JSON_OBJECT('id', id, 'user_id', user_id))")
                    ->tap(new WherePublicIdScope(BookmarkPublicIdsCollection::fromRequest($bookmarkIDs)->values())),
                'folderBookmarks' => FolderBookmark::query()
                    ->selectRaw("JSON_ARRAYAGG(JSON_OBJECT('id', id))")
                    ->whereColumn('folder_id', 'folders.id')
                    ->whereRaw("`bookmark_id` MEMBER OF(JSON_EXTRACT(bookmarks, '$[*].id'))"),
            ])
            ->tap(new WherePublicIdScope($folderID))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureBookmarksExistsInFolder($folder, $bookmarkIDs);

        $this->ensureCannotHideCollaboratorBookmarks($folder);

        $this->performUpdate($folder->folderBookmarks);
    }

    private function ensureCannotHideCollaboratorBookmarks(Folder $folder): void
    {
        $bookmarks = $folder->bookmarks ?? new Collection();

        try {
            $bookmarks = $bookmarks->mapInto(Bookmark::class)->each(function (Bookmark $bookmark) {
                BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
            });
        } catch (BookmarkNotFoundException) {
            throw HttpException::forbidden(['message' => 'CannotHideCollaboratorBookmarks']);
        }
    }

    private function ensureBookmarksExistsInFolder(Folder $folder, array $bookmarkIDs): void
    {
        $folderBookmarks = $folder->folderBookmarks ?? new Collection();

        if ($folderBookmarks->count() !== count($bookmarkIDs)) {
            throw new BookmarkNotFoundException();
        }
    }

    private function performUpdate(Collection $folderBookmarks): void
    {
        (new EloquentCollection($folderBookmarks->mapInto(FolderBookmark::class)))
            ->toQuery()
            ->update(['visibility' => FolderBookmarkVisibility::PRIVATE->value]);
    }
}
