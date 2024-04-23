<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\BookmarkPublicIdsCollection;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\Rules\PublicId\BookmarkPublicIdRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFavoriteController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'bookmarks'   => ['required', 'array', 'max:50', 'filled'],
            'bookmarks.*' => [new BookmarkPublicIdRule(), 'distinct:strict'],
        ]);

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromRequest($request->input('bookmarks'))->values();

        Favorite::where('user_id', User::fromRequest($request)->id)
            ->whereIn('bookmark_id', Bookmark::select('id')->tap(new WherePublicIdScope($bookmarksPublicIds)))
            ->get(['bookmark_id', 'id'])
            ->tap(function (Collection $favorites) use ($bookmarksPublicIds) {
                if ($favorites->count() !== $bookmarksPublicIds->count()) {
                    throw new BookmarkNotFoundException();
                }

                $favorites->toQuery()->delete();
            });

        return new JsonResponse();
    }
}
