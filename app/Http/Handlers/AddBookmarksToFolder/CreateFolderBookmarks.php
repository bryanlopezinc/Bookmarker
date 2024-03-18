<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Actions\CreateFolderBookmarks as CreateFolderBookmarksAction;
use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class CreateFolderBookmarks implements FolderRequestHandlerInterface
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        (new CreateFolderBookmarksAction())->create(
            $folder->id,
            $this->data->bookmarkIds,
            $this->data->makeHidden
        );

        $folder->touch();
    }
}
