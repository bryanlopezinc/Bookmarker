<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookmarkNotFoundException;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\PaginationData;
use App\Repositories\BookmarkRepository;
use App\ValueObjects\UserId;
use Illuminate\Http\Request;

final class FetchDuplicateBookmarksController
{
    public function __invoke(Request $request, BookmarkRepository $repository): ResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $bookmark = $repository->findById(intval($request->route('bookmark_id')), ['url_canonical_hash', 'user_id', 'id']);

        BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);

        $result = $repository->fetchPossibleDuplicates(
            $bookmark,
            UserId::fromAuthUser()->value(),
            PaginationData::fromRequest($request)
        );

        return new ResourceCollection($result, BookmarkResource::class);
    }
}
