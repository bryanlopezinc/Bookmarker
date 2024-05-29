<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use Illuminate\Support\Arr;

final class DeleteFolderBookmarks
{
    /**
     * @param array<Bookmark> $folderBookmarks
     */
    public function __construct(private readonly array $folderBookmarks)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $deleted = FolderBookmark::query()
            ->where('folder_id', $folder->id)
            ->whereIn('bookmark_id', Arr::pluck($this->folderBookmarks, 'id'))
            ->delete();

        if ($deleted > 0) {
            $folder->updated_at = now();

            $folder->save();
        }
    }
}
