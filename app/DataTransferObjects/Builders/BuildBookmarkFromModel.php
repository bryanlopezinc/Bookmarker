<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\Models\Bookmark;

final class BuildBookmarkFromModel
{
    public function __invoke(Bookmark $model): BookmarkBuilder
    {
        $attributes = $model->toArray();

        $keyExists = fn (string $key) => array_key_exists($key, $attributes);

        return (new BookmarkBuilder())
            ->when($keyExists('id'), fn (BookmarkBuilder $b) => $b->id($model->id))
            ->when($keyExists('description'), fn (BookmarkBuilder $b) => $b->description($model->description))
            ->when($keyExists('description_set_by_user'), fn (BookmarkBuilder $b) => $b->descriptionWasSetByUser($model->getAttribute('description_set_by_user')))
            ->when($keyExists('title'), fn (BookmarkBuilder $b) => $b->title($model->title))
            ->when($keyExists('has_custom_title'), fn (BookmarkBuilder $b) => $b->hasCustomTitle($model->getAttribute('has_custom_title')))
            ->when($keyExists('url'), fn (BookmarkBuilder $b) => $b->url($model->url))
            ->when($keyExists('preview_image_url'), fn (BookmarkBuilder $b) => $b->previewImageUrl((string)$model->preview_image_url))
            ->when($keyExists('site_id'), fn (BookmarkBuilder $b) => $b->siteId($model->site_id))
            ->when($keyExists('user_id'), fn (BookmarkBuilder $b) => $b->bookmarkedById($model->user_id))
            ->when($keyExists('created_at'), fn (BookmarkBuilder $b) => $b->bookmarkedOn((string)$model->created_at))
            ->when($keyExists('updated_at'), fn (BookmarkBuilder $b) => $b->updatedAt((string)$model->updated_at))
            ->when($keyExists('tags'), $this->tagsBuilderCallback($attributes))
            ->when($keyExists('site'), $this->siteBuilderCallback($model));
    }

    private function siteBuilderCallback(Bookmark $bookmark): callable
    {
        return function (BookmarkBuilder $b) use ($bookmark) {
            return $b->site(SiteBuilder::fromModel($bookmark->getRelation('site'))->build());
        };
    }

    private function tagsBuilderCallback(array $attributes): callable
    {
        return function (BookmarkBuilder $b) use ($attributes) {
            return $b->tags(collect($attributes['tags'])->pluck('name')->all());
        };
    }
}
