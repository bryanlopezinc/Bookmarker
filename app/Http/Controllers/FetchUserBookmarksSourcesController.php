<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\SourceResource;
use App\Models\Source;
use App\PaginationData;
use Illuminate\Http\Request;

/**
 * Fetch all the websites user has bookmarked a page from.
 */
final class FetchUserBookmarksSourcesController
{
    public function __invoke(Request $request): PaginatedResourceCollection
    {
        $request->validate(
            PaginationData::new()->maxPerPage(50)->asValidationRules()
        );

        $pagination = PaginationData::fromRequest($request);

        $sources = Source::select('bookmarks_sources.public_id', 'host', 'name')
            ->join('bookmarks', 'bookmarks_sources.id', '=', 'bookmarks.source_id')
            ->groupBy('bookmarks_sources.id')
            ->where('user_id', auth()->id())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return new PaginatedResourceCollection($sources, SourceResource::class);
    }
}
