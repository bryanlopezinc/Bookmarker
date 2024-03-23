<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData as Data;
use App\Exceptions\HttpException;
use App\Models\FolderBookmark;
use App\Models\Folder;

final class FolderContainsBookmarksConstraint implements FolderRequestHandlerInterface
{
    /**
     * @param array<FolderBookmark> $folderBookmarks
     */
    public function __construct(private readonly Data $data, private readonly array $folderBookmarks)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if (count($this->data->bookmarkIds) !== count($this->folderBookmarks)) {
            throw HttpException::notFound([
                'message' => 'BookmarkNotFound',
                'info' => 'The request could not be completed because folder does not contain bookmarks.'
            ]);
        }
    }
}
