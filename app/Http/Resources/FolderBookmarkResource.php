<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\FolderBookmark;
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
        data_set($bookmarkResource, 'attributes.visibility', $this->folderBookmark->visibility->value);
        data_set($bookmarkResource, 'attributes.can_favorite', $this->canFavoriteBookmark());
        data_set(
            $bookmarkResource,
            'attributes.belongs_to_auth_user',
            auth('api')->id() === $this->folderBookmark->bookmark->user_id
        );

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
            return $bookmark->user_id === auth('api')->id();
        }

        return false;
    }
}
