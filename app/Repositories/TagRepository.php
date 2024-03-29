<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tag as Model;
use Illuminate\Support\Collection;
use App\Models\Bookmark;
use App\Models\Taggable;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class TagRepository
{
    /**
     * @param array<string|Model> $tags
     */
    public function attach(array|Model $tags, Bookmark $bookmark): void
    {
        $tags = collect(Arr::wrap($tags))->map(function (Model|string $tag) {
            return is_string($tag) ? $tag : $tag->name;
        });

        if ($tags->isEmpty()) {
            return;
        }

        if (filled($tagIds = $this->insertGetIDs($tags->all()))) {
            Taggable::insert(array_map(fn (int $tagID) => [
                'taggable_id' => $bookmark->id,
                'tag_id'      => $tagID
            ], $tagIds));
        }
    }

    /**
     * Save new tags and return their ids with existing tag ids
     *
     * @param array<string> $tags
     *
     * @return array<int>
     */
    private function insertGetIDs(array $tags): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection */
        $existingTags = Model::whereIn('name', $tags)->get();

        //All tags exists. Nothing to insert.
        if ($existingTags->count() === count($tags)) {
            return $existingTags->pluck('id')->all();
        }

        $newTags = collect($tags)
            ->mapWithKeys(fn (string $tag) => [$tag => $tag])
            ->except($existingTags->pluck('name'))
            ->tap(fn (Collection $tags) => Model::insert(
                $tags->map(fn (string $tag) => ['name' => $tag])->all()
            ));

        return $existingTags->merge(
            Model::whereIn('name', $newTags->all())->get()
        )->pluck('id')->all();
    }

    /**
     * @return Paginator<Model>
     */
    public function getUserTags(int $userId, PaginationData $pagination, string $search = null): Paginator
    {
        return Model::query()
            ->select('name', DB::raw('COUNT(*) as bookmarksWithTag'))
            ->join('taggables', 'tag_id', '=', 'tags.id')
            ->when($search, function ($query) use ($search) {
                $query->where('tags.name', 'LIKE', "$search%");
            })
            ->whereExists(function (&$query) use ($userId) {
                $query = Bookmark::query()
                    ->select('id')
                    ->whereRaw('id = taggables.taggable_id')
                    ->where('user_id', $userId)
                    ->getQuery();
            })
            ->groupBy(['name'])
            ->simplePaginate($pagination->perPage(), page: $pagination->page());
    }

    /**
     * @param array<string> $tags
     */
    public function detach(array $tags, int $resourceID): void
    {
        Taggable::query()
            ->where('taggable_id', $resourceID)
            ->whereIn('tag_id', Model::select('id')->whereIn('name', $tags))
            ->delete();
    }
}
