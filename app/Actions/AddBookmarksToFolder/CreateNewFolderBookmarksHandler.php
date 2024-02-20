<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\FolderBookmark;
use Illuminate\Http\Request;
use App\Enums\FolderBookmarkVisibility as Visibility;
use Illuminate\Support\Collection;

final class CreateNewFolderBookmarksHandler implements HandlerInterface
{
    private readonly Request $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request ?: app('request');
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        $this->create($folder->id, $bookmarkIds, $this->request->input('make_hidden', []));

        $folder->touch();
    }

    /**
     * @param int|array<int> $bookmarkIds
     * @param array<int> $hidden
     */
    public function create(int $folderId, array|int $bookmarkIds, array $hidden = []): void
    {
        $makeHidden = collect($hidden)->map(fn ($bookmarkId) => (int) $bookmarkId);

        collect((array)$bookmarkIds)
            ->map(fn (int $bookmarkID) => [
                'bookmark_id' => $bookmarkID,
                'folder_id'   => $folderId,
                'visibility'  => $makeHidden->containsStrict($bookmarkID) ?
                    Visibility::PRIVATE->value :
                    Visibility::PUBLIC->value
            ])
            ->tap(fn (Collection $data) => FolderBookmark::insert($data->all()));
    }
}
