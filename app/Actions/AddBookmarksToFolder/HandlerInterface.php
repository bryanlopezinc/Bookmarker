<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Models\Folder;
use App\Exceptions\AddBookmarksToFolderException;

interface HandlerInterface
{
    /**
     * @param array<int> $bookmarkIds
     *
     * @throws AddBookmarksToFolderException
     */
    public function handle(Folder $folder, array $bookmarkIds): void;
}
