<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\FolderBookmark;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Collection;

final class DeleteFolderBookmarks implements FolderRequestHandlerInterface
{
    /**
<<<<<<< HEAD
     * @param array<FolderBookmark> folderBookmarks
=======
     * @param array<FolderBookmark> $folderBookmarks
>>>>>>> metrics
     */
    public function __construct(private readonly array $folderBookmarks)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $deleted = (new Collection($this->folderBookmarks))->toQuery()->delete();

        if ($deleted > 0) {
            $folder->updated_at = now();

            $folder->save();
        }
    }
}
