<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\FolderBookmark;
use App\ValueObjects\UserID;
use Illuminate\Http\Resources\Json\JsonResource;

final class FolderBookmarkResource extends JsonResource
{
    public function __construct(private FolderBookmark $folderBookmark)
    {
        parent::__construct($folderBookmark);
    }

    public function toArray($request)
    {
        $bookmarkResource = (new BookmarkResource($this->folderBookmark->bookmark))->toArray($request);

        data_set($bookmarkResource, 'type', 'folderBookmark');
        data_set($bookmarkResource, 'attributes.is_public', $this->folderBookmark->isPublic);
        data_set($bookmarkResource, 'attributes.can_favorite', $this->canFavoriteBookmark());

        return $bookmarkResource;
    }

    private function canFavoriteBookmark(): bool
    {
        $bookmark = $this->folderBookmark->bookmark;
        $isLoggedIn = auth('api')->check();

        if ($bookmark->isUserFavorite) {
            return false;
        }

        if ($isLoggedIn) {
            return $bookmark->ownerId->equals(UserID::fromAuthUser());
        }

        return false;
    }
}
