<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\TagRepository;
use App\Rules\TagRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchUserTagsController
{
    public function __invoke(Request $request, TagRepository $tagsRepository): JsonResponse
    {
        $request->validate(['tag' => ['required', new TagRule]]);

        $data = $tagsRepository->search($request->input('tag'), auth('api')->id(), 50)
            ->map(fn (string $tag) => ['name' => $tag])
            ->all();

        return response()->json(['data' => $data]);
    }
}
