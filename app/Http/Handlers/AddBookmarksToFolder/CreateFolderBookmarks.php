<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Actions\CreateFolderBookmarks as CreateFolderBookmarksAction;
use App\Models\Bookmark;
use App\Models\Folder;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use Illuminate\Support\Arr;

final class CreateFolderBookmarks
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(private readonly array $bookmarks, public readonly Data $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        (new CreateFolderBookmarksAction())->create(
            $folder->id,
            Arr::pluck($this->bookmarks, 'id'),
            $this->getBookmarkIdsToBeMarkedAsHidden()
        );

        $folder->touch();
    }

    private function getBookmarkIdsToBeMarkedAsHidden(): array
    {
        return collect($this->bookmarks)
            ->filter(fn (Bookmark $bookmark) => in_array($bookmark->public_id->present(), $this->data->makeHidden, true))
            ->pluck('id')
            ->all();
    }
}
