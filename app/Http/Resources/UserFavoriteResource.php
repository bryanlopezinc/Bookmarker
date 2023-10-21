<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Bookmark;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserFavoriteResource extends JsonResource
{
    private BookmarkResource $bookmarkResource;

    public function __construct(Bookmark $bookmark)
    {
        $bookmark->isUserFavorite = true;

        $this->bookmarkResource = new BookmarkResource($bookmark);
    }

    public function toArray($request)
    {
        return $this->bookmarkResource->toArray($request);
    }
}
