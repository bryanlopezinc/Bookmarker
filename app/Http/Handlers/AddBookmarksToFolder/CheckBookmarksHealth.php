<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\Bookmark;
use App\Jobs\CheckBookmarksHealth as CheckBookmarksHealthJob;

final class CheckBookmarksHealth implements FolderRequestHandlerInterface
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(private readonly array $bookmarks)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        dispatch(new CheckBookmarksHealthJob($this->bookmarks));
    }
}
