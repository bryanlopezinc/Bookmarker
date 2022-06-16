<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark as Model;
use App\Models\UserBookmarksCount;
use App\Models\WebSite;
use App\ValueObjects\UserID;

final class CreateBookmarkRepository
{
    public function __construct(private TagsRepository $tagsRepository)
    {
    }

    public function create(Bookmark $bookmark): Bookmark
    {
        $site = WebSite::query()->firstOrCreate(['host' => $bookmark->fromWebSite->domainName->value], [
            'host' => $bookmark->fromWebSite->domainName->value,
            'name' => $bookmark->fromWebSite->domainName->value
        ]);

        $model = Model::query()->create([
            'title' => $bookmark->title->value,
            'url'  => $bookmark->linkToWebPage->value,
            'description' => $bookmark->description->value,
            'description_set_by_user' => $bookmark->descriptionWasSetByUser,
            'site_id' => $site->id,
            'user_id' => $bookmark->ownerId->toInt(),
            'has_custom_title'  => $bookmark->hasCustomTitle,
            'preview_image_url' => $bookmark->hasPreviewImageUrl ? $bookmark->previewImageUrl->value : null
        ])->setRelation('site', $site);

        $this->tagsRepository->attach($bookmark->tags, $model);

        $this->incrementUserBookmarksCount($bookmark->ownerId);

        return BookmarkBuilder::fromModel($model)->build();
    }

    private function incrementUserBookmarksCount(UserID $userId): void
    {
        $bookmarksCount = UserBookmarksCount::query()->firstOrCreate(['user_id' => $userId->toInt()], ['count' => 1,]);

        if (!$bookmarksCount->wasRecentlyCreated) {
            $bookmarksCount->increment('count');
        }
    }
}