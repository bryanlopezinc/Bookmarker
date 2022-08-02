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
    public function __construct(private TagRepository $tagsRepository)
    {
    }

    public function update(UpdateBookmarkData $data): Bookmark
    {
        /** @var Model */
        $model = Model::query()
            ->whereKey($data->bookmark->id->toInt())
            ->first(['title', 'has_custom_title', 'description', 'description_set_by_user', 'user_id', 'id']);

        if ($data->hasTags()) {
            $this->tagsRepository->attach($data->bookmark->tags, $model);
        }

        if ($data->hasTitle()) {
            $model->title = $data->bookmark->title->value;
            $model->has_custom_title = true;
        }

        if ($data->hasDescription()) {
            $model->description = $data->bookmark->description->value;
            $model->description_set_by_user = true;
        }

        if ($data->hasThumbnailUrl()) {
            $model->preview_image_url = $data->bookmark->thumbnailUrl->toString();
        }

        if ($data->hasCanonicalUrl()) {
            $model->url_canonical = $data->bookmark->canonicalUrl->toString();
        }

        if ($data->hasCanonicalUrlHash()) {
            $model->url_canonical_hash = (string)$data->bookmark->canonicalUrlHash;
        }

        if ($data->hasResolvedUrl()) {
            $model->resolved_url = $data->bookmark->resolvedUrl->toString();
        }

        if ($data->hasResolvedAt()) {
            $model->resolved_at = $data->bookmark->resolvedAt;
        }

        $model->save();

        return BookmarkBuilder::fromModel($model)->build();
    }
}
