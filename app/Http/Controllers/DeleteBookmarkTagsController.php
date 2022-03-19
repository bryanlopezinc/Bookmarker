<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\TagsCollection;
use App\Http\Requests\DeleteBookmarkTagsRequest;
use App\ValueObjects\ResourceId;
use Illuminate\Http\JsonResponse;
use App\Services\DeleteBookmarkTagsService;

final class DeleteBookmarkTagsController
{
    public function __invoke(DeleteBookmarkTagsRequest $request, DeleteBookmarkTagsService $service): JsonResponse
    {
        $service->delete(ResourceId::fromRequest($request), TagsCollection::createFromStrings($request->input('tags')));

        return response()->json(status: 204);
    }
}
