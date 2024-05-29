<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\BookmarkPublicIdsCollection;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Scopes\WherePublicIdScope;
use App\Repositories\BookmarkRepository;
use App\Rules\PublicId\BookmarkPublicIdRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

final class DeleteBookmarkController
{
    public function __invoke(Request $request, BookmarkRepository $bookmarksRepository): JsonResponse
    {
        $maxBookmarks = 50;

        $request->validate(
            rules: [
                'ids'   => ['required', 'array', "max:{$maxBookmarks}"],
                'ids.*' => [new BookmarkPublicIdRule(), 'distinct:strict']
            ],
            messages: ['max' => "cannot delete more than {$maxBookmarks} bookmarks in one request"]
        );

        $bookmarksPublicIds = BookmarkPublicIdsCollection::fromRequest($request->input('ids'))->values();

        Bookmark::select(['id', 'user_id'])
            ->tap(new WherePublicIdScope($bookmarksPublicIds))
            ->get()
            ->tap(function (Collection $bookmarks) use ($bookmarksPublicIds) {
                if ($bookmarks->count() !== $bookmarksPublicIds->count()) {
                    throw new BookmarkNotFoundException();
                }
            })->each(function (Bookmark $bookmark) {
                BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
            })
            ->toQuery()
            ->delete();

        return new JsonResponse();
    }
}
