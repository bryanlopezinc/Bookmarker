<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\DataTransferObjects\RemoveFolderBookmarksRequestData as Data;
use App\Exceptions\HttpException;
use App\Models\Bookmark;

final class FolderContainsBookmarksConstraint
{
    /**
     * @param array<Bookmark> $folderBookmarks
     */
    public function __construct(private readonly Data $data, private readonly array $folderBookmarks)
    {
    }

    public function __invoke(): void
    {
        if (count($this->data->bookmarkIds) !== count($this->folderBookmarks)) {
            throw HttpException::notFound([
                'message' => 'BookmarkNotFound',
                'info' => 'The request could not be completed because folder does not contain bookmarks.'
            ]);
        }
    }
}
