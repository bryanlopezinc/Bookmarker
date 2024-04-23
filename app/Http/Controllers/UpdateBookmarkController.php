<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Requests\CreateOrUpdateBookmarkRequest as Request;
use App\Services\UpdateBookmarkService;
use App\ValueObjects\PublicId\BookmarkPublicId;

final class UpdateBookmarkController
{
    public function __invoke(Request $request, UpdateBookmarkService $service, string $bookmarkId): JsonResponse
    {
        $service->fromRequest($request, BookmarkPublicId::fromRequest($bookmarkId));

        return new JsonResponse(['success']);
    }
}
