<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\TagsCollection;
use App\Http\Requests\DeleteBookmarkTagsRequest;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use App\Services\DeleteBookmarkTagsService;

final class DeleteBookmarkTagsController
{
    public function __invoke(DeleteBookmarkTagsRequest $request, DeleteBookmarkTagsService $service): JsonResponse
    {
        $service->delete(ResourceID::fromRequest($request), TagsCollection::createFromStrings($request->input('tags')));

        return response()->json();
    }
}
