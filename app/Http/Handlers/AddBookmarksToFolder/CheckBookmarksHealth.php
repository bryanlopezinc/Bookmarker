<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Jobs\CheckBookmarksHealth as CheckBookmarksHealthJob;

final class CheckBookmarksHealth
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(private readonly array $bookmarks)
    {
    }

    public function __invoke(Folder $folder): void
    {
        dispatch(new CheckBookmarksHealthJob($this->bookmarks));
    }
}
