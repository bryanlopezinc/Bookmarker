<?php

declare(strict_types=1);

namespace App\Repositories;

use App\ValueObjects\ResourceId;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\QueryColumns\BookmarkQueryColumns;

final class FindBookmarksRepository
{
    public function findById(ResourceId $bookmarkId, BookmarkQueryColumns $columns = new BookmarkQueryColumns()): Bookmark|false
    {
        $model = Model::WithQueryOptions($columns)->whereKey($bookmarkId->toInt())->first();

        if (!$columns->has('id') && !is_null($model)) {
            $model->offsetUnset('id');
        }

        return is_null($model) ? false : BookmarkBuilder::fromModel($model)->build();
    }
}
