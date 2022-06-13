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
        data_set($bookmarkResource, 'attributes.is_public', $this->folderBookmark->isPublic);

        return $bookmarkResource;
    }
}
