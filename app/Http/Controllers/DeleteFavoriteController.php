<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookmarkNotFoundException;
use App\Models\Favorite;
use App\Rules\ResourceIdRule;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFavoriteController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'bookmarks'   => ['required', 'array', 'max:50'],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict'],
        ]);

        Favorite::where('user_id', UserID::fromAuthUser()->value())
            ->whereIntegerInRaw('bookmark_id', $bookmarkIds = $request->input('bookmarks'))
            ->get(['bookmark_id', 'id'])
            ->tap(function (Collection $favorites) use ($bookmarkIds) {
                if ($favorites->count() !== count($bookmarkIds)) {
                    throw new BookmarkNotFoundException();
                }

                $favorites->toQuery()->delete();
            });

        return response()->json();
    }
}
