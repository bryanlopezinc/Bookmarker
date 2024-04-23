<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderBookmark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueFolderBookmarkConstraint implements Scope
{
    public function __construct(private readonly array $bookmarkIds)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->withCasts(['bookmarksAlreadyExists' => 'bool'])
            ->addSelect([
                'bookmarksAlreadyExists' => FolderBookmark::query()
                    ->selectRaw('COUNT(*) > 0')
                    ->whereColumn('folder_id', 'folders.id')
                    ->whereIntegerInRaw('bookmark_id', $this->bookmarkIds)
            ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->bookmarksAlreadyExists) {
            throw HttpException::conflict([
                'message' => 'FolderContainsBookmarks',
                'info' => 'The given bookmarks already exists in folder.'
            ]);
        }
    }
}
