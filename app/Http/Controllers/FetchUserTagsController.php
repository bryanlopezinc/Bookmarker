<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\TagResource;
use App\PaginationData;
use App\Repositories\TagRepository;
use App\ValueObjects\UserId;
use Illuminate\Http\Request;

final class FetchUserTagsController
{
    public function __invoke(Request $request, TagRepository $repository): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->maxPerPage(50)->asValidationRules());

        return new PaginatedResourceCollection(
            $repository->getUserTags(UserId::fromAuthUser()->value(), PaginationData::fromRequest($request)),
            TagResource::class
        );
    }
}
