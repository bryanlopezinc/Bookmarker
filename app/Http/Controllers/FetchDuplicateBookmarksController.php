<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BookmarkNotFoundException;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Models\Bookmark;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\PaginationData;
use App\Repositories\BookmarkRepository;
use App\ValueObjects\PublicId\BookmarkPublicId;
use Illuminate\Http\Request;

final class FetchDuplicateBookmarksController
{
    public function __invoke(Request $request, BookmarkRepository $repository, string $bookmarkId): ResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $bookmark = Bookmark::select(['url_canonical_hash', 'user_id', 'id'])
            ->tap(new WherePublicIdScope(BookmarkPublicId::fromRequest($bookmarkId)))
            ->firstOrNew();

        if( ! $bookmark->exists) {
            throw new BookmarkNotFoundException();
        }

        BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);

        $result = $repository->fetchPossibleDuplicates(
            $bookmark,
            User::fromRequest($request)->id,
            PaginationData::fromRequest($request)
        );

        return new ResourceCollection($result, BookmarkResource::class);
    }
}
