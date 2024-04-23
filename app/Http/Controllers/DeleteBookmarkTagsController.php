<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\TagRule;
use Illuminate\Http\JsonResponse;
use App\Services\DeleteBookmarkTagsService as Service;
use App\ValueObjects\PublicId\BookmarkPublicId;
use Illuminate\Http\Request;

final class DeleteBookmarkTagsController
{
    public function __invoke(Request $request, Service $service, string $bookmarkId): JsonResponse
    {
        $request->validate([
            'tags'   => ['required', 'filled', 'array'],
            'tags.*' => [new TagRule()],
        ]);

        $service->delete(BookmarkPublicId::fromRequest($bookmarkId), $request->input('tags'));

        return new JsonResponse();
    }
}
