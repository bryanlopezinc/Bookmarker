<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\CreateBookmarkRepositoryInterface;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark as Model;
use App\Models\UserBookmarksCount;
use App\Models\Source;
use App\ValueObjects\UserID;

final class CreateBookmarkRepository implements CreateBookmarkRepositoryInterface
{
    public function __construct(private TagRepository $tagsRepository)
    {
    }

    public function create(Bookmark $bookmark): Bookmark
    {
        $source = Source::query()->firstOrCreate(['host' => $bookmark->source->domainName->value], [
            'host' => $bookmark->source->domainName->value,
            'name' => $bookmark->source->domainName->value,
            'name_updated_at' => null
        ]);

        $model = Model::query()->create([
            'title' => $bookmark->title->value,
            'url'  => $bookmark->url->toString(),
            'description' => $bookmark->description->value,
            'description_set_by_user' => $bookmark->descriptionWasSetByUser,
            'source_id' => $source->id,
            'user_id' => $bookmark->ownerId->toInt(),
            'has_custom_title'  => $bookmark->hasCustomTitle,
            'preview_image_url' => $bookmark->hasThumbnailUrl ? $bookmark->thumbnailUrl->toString() : null,
            'created_at' => $bookmark->timeCreated,
            'url_canonical' => $bookmark->canonicalUrl->toString(),
            'url_canonical_hash' => (string) $bookmark->canonicalUrlHash,
            'resolved_url' => $bookmark->resolvedUrl->toString()
        ])->setRelation('source', $source);

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
