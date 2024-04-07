<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueFolderBookmarkConstraint implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly Data $data, private readonly int $folderId)
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->withCasts(['bookmarksAlreadyExists' => 'bool'])
            ->addSelect([
                'bookmarksAlreadyExists' => FolderBookmark::query()
                    ->selectRaw('COUNT(*) > 0')
                    ->where('folder_id', $this->folderId)
                    ->whereIntegerInRaw('bookmark_id', $this->data->bookmarkIds)
            ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($folder->bookmarksAlreadyExists) {
            throw HttpException::conflict([
                'message' => 'FolderContainsBookmarks',
                'info' => 'The given bookmarks already exists in folder.'
            ]);
        }
    }
}
