<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\UpdateBookmarkData;
use App\DataTransferObjects\Builders\BookmarkBuilder;

final class UpdateBookmarkRepository
{
    public function __construct(private SaveBookmarkTagsRepository $saveBookmarkTags)
    {
    }

    public function update(UpdateBookmarkData $data): Bookmark
    {
        /** @var Model */
        $model = Model::query()->whereKey($data->id->toInt())->first();

        $this->saveBookmarkTags->save($model, $data->tags);

        if ($data->hasTitle) {
            $model->title = $data->title->value;
            $model->has_custom_title = true;
        }

        if ($data->hasDescription) {
            $model->description = $data->description->value;
            $model->description_set_by_user = true;
        }

        $model->save();

        return BookmarkBuilder::fromModel($model)->build();
    }
}
