<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FolderBookmark;
use App\Enums\FolderBookmarkVisibility as Visibility;
use Illuminate\Support\Collection;

final class CreateFolderBookmarks
{
    /**
     * @param int|array<int> $bookmarkIds
     * @param array<int>     $hidden
     */
    public function create(int $folderId, array|int $bookmarkIds, array $hidden = []): void
    {
        $makeHidden = collect($hidden)->map(fn ($bookmarkId) => (int) $bookmarkId);

        collect((array)$bookmarkIds)
            ->map(fn (int $bookmarkID) => [
                'bookmark_id' => $bookmarkID,
                'folder_id'   => $folderId,
                'visibility'  => $makeHidden->containsStrict($bookmarkID) ?
                    Visibility::PRIVATE->value :
                    Visibility::PUBLIC->value
            ])
            ->tap(fn (Collection $data) => FolderBookmark::insert($data->all()));
    }
}
