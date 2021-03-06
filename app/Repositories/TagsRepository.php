<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag as Model;
use Illuminate\Support\Collection;
use App\Collections\TagsCollection;
use App\Contracts\TaggableInterface;
use App\Models\Taggable;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\Tag;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class TagsRepository
{
    public function attach(TagsCollection $tags, TaggableInterface $tagable): void
    {
        if ($tags->isEmpty()) {
            return;
        }

        if (filled($tagIds = $this->insertGetTagIds($tags))) {
            Taggable::insert(array_map(fn (int $tagID) => [
                'taggable_id' => $tagable->taggableID()->toInt(),
                'taggable_type' => $tagable->taggableType()->type(),
                'tagged_by_id' => $tagable->taggedBy()->toInt(),
                'tag_id' => $tagID
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
        /** @var \Illuminate\Database\Eloquent\Collection */
        $existingTags = Model::whereIn('name', $tags->toStringCollection()->all())->get();

        //All tags exists. Nothing to insert.
        if ($existingTags->count() === $tags->count()) {
            return $existingTags->pluck('id')->all();
        }

        $newTags = $tags
            ->except(TagsCollection::make($existingTags))
            ->toStringCollection()
            ->tap(fn (Collection $tags) => Model::insert(
                $tags->map(fn (string $tag) => ['name' => $tag])->all()
            ));

        return $existingTags->merge(Model::whereIn('name', $newTags->all())->get())->pluck('id')->all();
    }

    /**
     * Search for tags that was created by user.
     */
    public function search(string $tag, UserID $userID, int $limit): TagsCollection
    {
        return Model::join('taggables', 'tags.id', '=', 'taggables.tag_id')
            ->where('tagged_by_id', $userID->toInt())
            ->where('tags.name', 'LIKE', "%$tag%")
            ->orderByDesc('tags.id')
            ->limit($limit)
            ->get()
            ->pipe(fn (Collection $tags) => TagsCollection::make($tags));
    }

    /**
     * @return Paginator<Tag>
     */
    public function getUsertags(UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = Model::join('taggables', 'tags.id', '=', 'taggables.tag_id')
            ->where('tagged_by_id', $userID->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(fn (Model $tag) => new Tag($tag->name))
        );
    }

    public function detach(TagsCollection $tags, ResourceID $resourceID): void
    {
        Taggable::query()
            ->where('taggable_id', $resourceID->toInt())
            ->whereIn('tag_id', Model::select('id')->whereIn('name', $tags->toStringCollection()->all()))
            ->delete();
    }
}
