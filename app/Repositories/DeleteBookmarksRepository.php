<?php

declare(strict_types=1);

namespace App\Repositories;

use App\ValueObjects\ResourceId;
use App\Models\Bookmark as Model;
use App\ValueObjects\UserId;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarksRepository
{
    public function __construct(private BookmarksCountRepository $bookmarksCountRepository)
    {
    }

    public function delete(ResourceId $bookmarkId, UserId $userId): bool
    {
        $recordsCount = Model::query()->where(['id' => $bookmarkId->toInt()])->delete();

        $this->bookmarksCountRepository->decrementUserBookmarksCount($userId);

        return (bool) $recordsCount;
    }

    /**
     * Delete all bookmarks from a particular site
     */
    public function fromSite(ResourceId $siteId, UserId $userId): bool
    {
        return Model::query()->where([
            'site_id' => $siteId->toInt(),
            'user_id' => $userId->toInt()
        ])->chunkById(100, function (Collection $chunk) use ($userId) {
            $chunk->toQuery()->delete();

            $this->bookmarksCountRepository->decrementUserBookmarksCount($userId, $chunk->count());
        });
    }
}
