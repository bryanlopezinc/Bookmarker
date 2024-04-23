<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\ValueObjects\FolderStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class MaxFolderBookmarksConstraint implements Scope
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->withCount('bookmarks')->addSelect(['settings']);
    }

    public function __invoke(Folder $folder): void
    {
        $storage = new FolderStorage($folder->bookmarks_count);

        if ( ! $storage->canContain($this->data->bookmarksPublicIds) || $folder->settings->maxBookmarksLimit >= $storage->total) {
            throw HttpException::forbidden([
                'message' => 'FolderBookmarksLimitReached',
                'info'    => 'Folder has reached its max bookmarks limit.'
            ]);
        }
    }
}
