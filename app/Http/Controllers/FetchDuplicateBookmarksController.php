<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Services\FetchDuplicateBookmarksService as Service;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Request;

final class FetchDuplicateBookmarksController
{
    public function __invoke(Request $request, Service $service): ResourceCollection
    {
        $request->validate([
            'id' => ['required', new ResourceIdRule()],
            ...PaginationData::new()->asValidationRules(),
        ]);

        $result = $service->fetch(ResourceID::fromRequest($request), PaginationData::fromRequest($request));

        return new ResourceCollection($result, BookmarkResource::class);
    }
}
