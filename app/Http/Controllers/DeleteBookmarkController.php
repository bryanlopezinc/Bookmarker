<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Repositories\BookmarkRepository;
use Illuminate\Http\Request;
use App\Rules\ResourceIdRule;
use Illuminate\Http\JsonResponse;

final class DeleteBookmarkController
{
    public function __invoke(Request $request, BookmarkRepository $bookmarksRepository): JsonResponse
    {
        $maxBookmarks = 50;

        $request->validate(
            rules: [
                'ids'   => ['required', 'array', "max:{$maxBookmarks}"],
                'ids.*' => [new ResourceIdRule(), 'distinct:strict']
            ],
            messages: ['max' => "cannot delete more than {$maxBookmarks} bookmarks in one request"]
        );

        $bookmarks = $bookmarksRepository->findManyById(
            $bookmarkIds = $request->input('ids'),
            ['user_id', 'id']
        );

        if ($bookmarks->count() !== count($bookmarkIds)) {
            throw new BookmarkNotFoundException();
        }

        $bookmarks->each(function (Bookmark $bookmark) {
            BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
        });

        Bookmark::whereIn('id', $bookmarkIds)->delete();

        return response()->json();
    }
}
