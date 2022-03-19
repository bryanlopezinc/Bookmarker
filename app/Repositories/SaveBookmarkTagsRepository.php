<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag;
use App\Models\BookmarkTag;
use App\Models\Bookmark as Model;
use Illuminate\Support\Collection;
use App\Collections\TagsCollection;

final class SaveBookmarkTagsRepository
{
    public function save(Model $bookmark, TagsCollection $tags): void
    {
        if ($tags->isEmpty()) {
            return;
        }

        if (filled($tagIds = $this->insertGetTagIds($tags))) {
            BookmarkTag::insert(array_map(fn (int $tagId) => [
                'bookmark_id' => $bookmark->id,
                'tag_id' => $tagId
            ], $tagIds));
        }
    }

    /**
     * Save new tags and return their ids with existing tag ids
     *
     * @return array<int>
     */
    private function insertGetTagIds(TagsCollection $tags): array
    {
        $bookmarkTags = $tags->unique();

        $savedTags = Tag::whereIn('name', $bookmarkTags->toStringCollection())->get();

        $newTags = $bookmarkTags
            ->except(TagsCollection::createFromStrings($savedTags->pluck('name')->all()))
            ->toStringCollection()
            ->tap(fn (Collection $tags) => Tag::insert($tags->map(fn (string $tag) => ['name' => $tag])->all()));

        return $savedTags->merge(Tag::whereIn('name', $newTags->all())->get())->pluck('id')->all();
    }
}
