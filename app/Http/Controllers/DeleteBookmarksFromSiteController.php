<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\DeleteBookmarksRepository;
use Illuminate\Http\Request;
use App\Rules\ResourceIdRule;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use App\ValueObjects\UserId;

final class DeleteBookmarksFromSiteController
{
    public function __invoke(Request $request, DeleteBookmarksRepository $repository): JsonResponse
    {
        $request->validate([
            'site_id' => ['required', new ResourceIdRule]
        ]);

        $repository->fromSite(ResourceID::fromRequest($request, 'site_id'), UserId::fromAuthUser());

        return response()->json(status: 202);
    }
}
