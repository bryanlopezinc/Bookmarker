<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CreateFavoritesResponse;
use App\Rules\ResourceIdRule;
use App\Services\CreateFavoriteService;
use App\ValueObjects\UserId;
use Illuminate\Http\Request;

final class CreateFavoriteController
{
    public function __invoke(Request $request, CreateFavoriteService $service): CreateFavoritesResponse
    {
        $maxBookmarks = 50;

        $request->validate([
            'bookmarks' => ['required', 'filled', join(':', ['max', $maxBookmarks])],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict']
        ], ['max' => "cannot add more than {$maxBookmarks} bookmarks simultaneously"]);


        return $service->create($request->input('bookmarks'), UserId::fromAuthUser()->value());
    }
}
