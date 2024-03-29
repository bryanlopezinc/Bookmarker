<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bookmark as Model;
use App\Models\Favorite;
use App\PaginationData;
use App\ValueObjects\UserId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use App\Http\Requests\FetchUserBookmarksRequest as Request;
use App\Models\BookmarkHealth;
use Illuminate\Http\Response;

final class FetchUserBookmarksService
{
    /**
     * @return Paginator<Model>
     */
    public function fromRequest(Request $request): Paginator
    {
        $model = new Model();

        $query = Model::WithQueryOptions()
            ->where('user_id', UserId::fromAuthUser()->value())
            ->addSelect([
                'isUserFavorite' => Favorite::query()
                    ->select('id')
                    ->whereRaw("bookmark_id = {$model->qualifyColumn('id')}")
            ]);

        $request->whenHas('source_id', function (string $id) use ($query) {
            $query->where('source_id', intval($id));
        });

        $request->whenHas('tags', function (array $tags) use ($query) {
            $query->whereHas('tags', function (Builder $builder) use ($tags) {
                $builder->whereIn('name', $tags);
            });
        });

        $query->when($request->boolean('untagged'), function ($query) {
            $query->whereDoesntHave('tags');
        });

        $request->whenHas('sort', function (string $criteria) use ($query) {
            $criteria === 'oldest' ? $query->oldest('id') : $query->latest('id');
        });

        $query->when($request->boolean('dead_links'), function ($query) use ($model) {
            $query->whereExists(function (&$query) use ($model) {
                $query = BookmarkHealth::query()
                    ->select('id')
                    ->whereBetween('status_code', [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS])
                    ->whereRaw("bookmark_id = {$model->qualifyColumn('id')}")
                    ->getQuery();
            });
        });

        return $this->paginate($query, PaginationData::fromRequest($request));
    }

    /**
     * @param Builder $query
     */
    private function paginate($query, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(function (Model $model) {
                $model->isUserFavorite = (bool) $model->isUserFavorite;

                return $model;
            })
        );
    }
}
