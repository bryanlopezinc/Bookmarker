<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\UpdateBookmarkData;
use App\DataTransferObjects\Builders\BookmarkBuilder;

final class UpdateBookmarkRepository implements UpdateBookmarkRepositoryInterface
{
    public function __construct(private TagsRepository $tagsRepository)
    {
    }

    public function update(UpdateBookmarkData $data): Bookmark
    {
        /** @var Model */
        $model = Model::query()
            ->whereKey($data->id->toInt())
            ->first(['title', 'has_custom_title', 'description', 'description_set_by_user', 'user_id', 'id']);

        $this->tagsRepository->attach($data->tags, $model);

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
