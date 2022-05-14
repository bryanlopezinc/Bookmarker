<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\TagResource;
use App\PaginationData;
use App\Repositories\TagsRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchUserTagsController
{
    public function __invoke(Request $request, TagsRepository $repository)
    {
        $request->validate(PaginationData::new()->maxPerPage(50)->asValidationRules());

        return new PaginatedResourceCollection(
            $repository->getUsertags(UserID::fromAuthUser(), PaginationData::fromRequest($request)),
            TagResource::class
        );
    }
}
