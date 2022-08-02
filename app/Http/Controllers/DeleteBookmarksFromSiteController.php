<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\DeleteBookmarkRepository;
use Illuminate\Http\Request;
use App\Rules\ResourceIdRule;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use App\ValueObjects\UserID;

/**
 * Delete all bookmarks that were added from a particular site.
 */
final class DeleteBookmarksFromSiteController
{
    public function __invoke(Request $request, DeleteBookmarkRepository $repository): JsonResponse
    {
        $request->validate([
            'site_id' => ['required', new ResourceIdRule]
        ]);

        $repository->fromSite(ResourceID::fromRequest($request, 'site_id'), UserID::fromAuthUser());

        return response()->json();
    }
}
