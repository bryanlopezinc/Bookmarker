<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarkRepository
{
    public function delete(ResourceIDsCollection $bookmarkIDs): bool
    {
        return (bool) Model::whereIn('id', $bookmarkIDs->asIntegers())->delete();
    }

    /**
     * Delete all bookmarks from a particular site
     */
    public function fromSource(ResourceID $sourceID, UserID $userId): bool
    {
        return Model::query()->where([
            'source_id' => $sourceID->value(),
            'user_id' => $userId->value()
        ])->chunkById(100, function (Collection $chunk) {
            $chunk->toQuery()->delete();
        });
    }
}
