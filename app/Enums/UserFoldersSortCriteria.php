<?php

declare(strict_types=1);

namespace App\Enums;

use App\Http\Requests\FetchUserFoldersRequest;

enum UserFoldersSortCriteria
{
    case OLDEST;
    case NEWEST;
    case MOST_ITEMS;
    case LEAST_ITEMS;
    case RECENTLY_UPDATED;

    public static function fromRequest(FetchUserFoldersRequest $request): self
    {
        return match ($request->input('sort')) {
            'oldest', => self::OLDEST,
            'newest' => self::NEWEST,
            'most_items' => self::MOST_ITEMS,
            'least_items' => self::LEAST_ITEMS,
            'updated_recently' => self::RECENTLY_UPDATED,
            default => self::NEWEST
        };
    }
}
