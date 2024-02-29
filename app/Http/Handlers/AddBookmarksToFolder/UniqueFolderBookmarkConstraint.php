<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class UniqueFolderBookmarkConstraint implements FolderRequestHandlerInterface
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $hasBookmarks = FolderBookmark::query()
            ->where('folder_id', $folder->id)
            ->whereIntegerInRaw('bookmark_id', $this->data->bookmarkIds)
            ->count() > 0;

        if ($hasBookmarks) {
            throw HttpException::conflict([
                'message' => 'FolderContainsBookmarks',
                'info' => 'The given bookmarks already exists in folder.'
            ]);
        }
    }
}
