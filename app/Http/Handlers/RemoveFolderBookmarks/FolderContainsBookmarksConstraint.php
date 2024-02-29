<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData;
use App\Exceptions\HttpException;
use App\Models\FolderBookmark;
use App\Models\Folder;

final class FolderContainsBookmarksConstraint implements FolderRequestHandlerInterface, FolderBookmarksAwareInterface
{
    /** @var array<FolderBookmark> */
    private array $folderBookmark;

    private readonly RemoveFolderBookmarksRequestData $data;

    public function __construct(RemoveFolderBookmarksRequestData $data)
    {
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function setBookmarks(array $folderBookmark): void
    {
        $this->folderBookmark = $folderBookmark;
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if (count($this->data->bookmarkIds) !== count($this->folderBookmark)) {
            throw HttpException::notFound([
                'message' => 'BookmarkNotFound',
                'info' => 'The request could not be completed because folder does not contain bookmarks.'
            ]);
        }
    }
}
