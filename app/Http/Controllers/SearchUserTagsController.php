<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\TagsRepository;
use App\ValueObjects\Tag;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchUserTagsController
{
    public function __invoke(Request $request, TagsRepository $tagsRepository): JsonResponse
    {
        $request->validate([
            'tag' => Tag::rules(['required'])
        ]);

        $data =  $tagsRepository->search($request->input('tag'), UserID::fromAuthUser(), 30)
            ->toStringCollection()
            ->map(fn (string $tag) => ['name' => $tag])
            ->all();

        return response()->json(['data' => $data]);
    }
}
