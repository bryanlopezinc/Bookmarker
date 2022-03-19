<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag;
use App\Models\BookmarkTag;
use App\Collections\TagsCollection;
use App\ValueObjects\ResourceId;

final class DeleteBookmarkTagsRepository
{
    public function delete(ResourceId $bookmarkId, TagsCollection $tags): void
    {
        BookmarkTag::query()->where('bookmark_id', $bookmarkId->toInt())
            ->whereIn('tag_id', Tag::select('id')->whereIn('name', $tags->toStringCollection()->all()))
            ->delete();
    }
}
