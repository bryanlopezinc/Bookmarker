<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\TagsCollection;
use App\Rules\ResourceIdRule;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use App\Services\Folder\DetachFolderTagsService as Service;
use App\ValueObjects\Tag;
use Illuminate\Http\Request;

final class DeleteFolderTagsController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'id' => ['required', new ResourceIdRule()],
            'tags' => ['required', 'filled', 'array'],
            'tags.*' => Tag::rules(),
        ]);

        $service->delete(ResourceID::fromRequest($request), TagsCollection::make($request->input('tags')));

        return response()->json();
    }
}
