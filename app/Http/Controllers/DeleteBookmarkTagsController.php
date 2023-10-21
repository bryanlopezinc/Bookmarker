<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBookmarkTagsRequest as Request;
use Illuminate\Http\JsonResponse;
use App\Services\DeleteBookmarkTagsService as Service;

final class DeleteBookmarkTagsController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $service->delete($request->integer('id'), $request->input('tags'));

        return response()->json();
    }
}
