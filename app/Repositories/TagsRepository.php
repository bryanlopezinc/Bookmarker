<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag as Model;
use App\Models\BookmarkTag;
use App\Models\Bookmark;
use Illuminate\Support\Collection;
use App\Collections\TagsCollection;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\Tag;
use App\ValueObjects\UserID;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\Paginator;

final class TagsRepository
{
    public function attach(TagsCollection $tags, Bookmark $bookmark): void
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
        $savedTags = Model::whereIn('name', $tags->toStringCollection())->get();

        $newTags = $tags
            ->except(TagsCollection::createFromStrings($savedTags->pluck('name')->all()))
            ->toStringCollection()
            ->tap(fn (Collection $tags) => Model::insert($tags->map(fn (string $tag) => ['name' => $tag])->all()));

        return $savedTags->merge(Model::whereIn('name', $newTags->all())->get())->pluck('id')->all();
    }

    /**
     * Search for tags that was created by user.
     */
    public function search(string $tag, UserID $userId, int $limit): TagsCollection
    {
        return Model::join('bookmarks_tags', function (JoinClause $join) use ($userId) {
            $join->on('tags.id', '=', 'bookmarks_tags.tag_id')
                ->join('bookmarks', 'bookmarks.id', '=', 'bookmarks_tags.bookmark_id')
                ->where('bookmarks.user_id', $userId->toInt());
        })
            ->where('tags.name', 'LIKE', "%$tag%")
            ->orderByDesc('tags.id')
            ->limit($limit)
            ->get()
            ->pipe(fn (Collection $tags) => TagsCollection::createFromStrings($tags->pluck('name')->all()));
    }

    /**
     * @return Paginator<Tag>
     */
    public function getUsertags(UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = Model::join('bookmarks_tags', function (JoinClause $join) use ($userID) {
            $join->on('tags.id', '=', 'bookmarks_tags.tag_id')
                ->join('bookmarks', 'bookmarks.id', '=', 'bookmarks_tags.bookmark_id')
                ->where('bookmarks.user_id', $userID->toInt());
        })->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(fn (Model $tag) => new Tag($tag->name))
        );
    }

    public function detach(TagsCollection $tags, ResourceID $bookmarkId,): void
    {
        BookmarkTag::query()->where('bookmark_id', $bookmarkId->toInt())
            ->whereIn('tag_id', Model::select('id')->whereIn('name', $tags->toStringCollection()->all()))
            ->delete();
    }
}
