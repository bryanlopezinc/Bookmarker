<?php

declare(strict_types=1);

namespace App\Repositories;

use App\BookmarkColumns;
use App\ValueObjects\ResourceId;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;

final class FindBookmarksRepository
{
    public function findById(ResourceId $bookmarkId, BookmarkColumns $columns = new BookmarkColumns): Bookmark|false
    {
        $model = Model::WithQueryOptions($columns)->whereKey($bookmarkId->toInt())->first();

        if (!$columns->has('id') && !is_null($model)) {
            $model->offsetUnset('id');
        }

        return is_null($model) ? false : BookmarkBuilder::fromModel($model)->build();
    }
}
