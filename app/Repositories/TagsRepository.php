<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag;
use App\Models\BookmarkTag;
use App\Models\Bookmark as Model;
use Illuminate\Support\Collection;
use App\Collections\TagsCollection;
use App\ValueObjects\ResourceId;
use App\ValueObjects\UserId;
use Illuminate\Database\Query\JoinClause;

final class TagsRepository
{
    public function attach(TagsCollection $tags, Model $bookmark): void
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

    /**
     * Search for tags that was created by user.
     */
    public function search(string $tag, UserId $userId, int $limit): TagsCollection
    {
        return Tag::join('bookmarks_tags', function (JoinClause $join) use ($userId) {
            $join->on('tags.id', '=', 'bookmarks_tags.tag_id')
                ->join('bookmarks', 'bookmarks.id', '=', 'bookmarks_tags.bookmark_id')
                ->where('bookmarks.user_id', $userId->toInt());
        })
            ->where('tags.name', 'LIKE', "%$tag%")
            ->orderByDesc('tags.id')
            ->limit($limit)
            ->get()
            ->map(fn (Tag $tag) => $tag->name)
            ->pipe(fn (Collection $tags) => TagsCollection::createFromStrings($tags->all()));
    }

    public function detach(TagsCollection $tags, ResourceId $bookmarkId,): void
    {
        BookmarkTag::query()->where('bookmark_id', $bookmarkId->toInt())
            ->whereIn('tag_id', Tag::select('id')->whereIn('name', $tags->toStringCollection()->all()))
            ->delete();
    }
}
