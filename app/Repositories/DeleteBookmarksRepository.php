<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarksRepository
{
    public function __construct(private BookmarksCountRepository $bookmarksCountRepository)
    {
    }

    public function deleteMany(ResourceIDsCollection $bookmarkIds, UserID $userId): bool
    {
        $recordsCount = Model::query()->whereIn('id', $bookmarkIds->asIntegers())->delete();

        if ($recordsCount > 0) {
            $this->bookmarksCountRepository->decrementUserBookmarksCount($userId, $recordsCount);
        }

        return (bool) $recordsCount;
    }

    /**
     * Delete all bookmarks from a particular site
     */
    public function fromSite(ResourceID $siteId, UserID $userId): bool
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
