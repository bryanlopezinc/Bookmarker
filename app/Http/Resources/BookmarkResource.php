<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\Bookmark;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookmarkResource extends JsonResource
{
    public function __construct(private Bookmark $bookmark)
    {
        parent::__construct($bookmark);
    }

    public function toArray($request)
    {
        return [
            'type' => 'bookmark',
            'attributes'      => [
                'id'                 => $this->bookmark->id->toInt(),
                'title'              => $this->bookmark->title->value,
                'web_page_link'      => $this->bookmark->linkToWebPage->value,
                'has_preview_image'  => $this->bookmark->hasPreviewImageUrl,
                'preview_image_url'  => $this->when($this->bookmark->hasPreviewImageUrl, fn () => $this->bookmark->previewImageUrl->value),
                'description'        => $this->when(!$this->bookmark->description->isEmpty(), fn () => $this->bookmark->description->value),
                'has_description'    => !$this->bookmark->description->isEmpty(),
                'site_id'            => $this->bookmark->webPagesiteId->toInt(),
                'bookmarked_on'      => $this->bookmark->timeCreated->timeStamp->diffForHumans(),
                'from_site'          => new WebSiteResource($this->bookmark->fromWebSite),
                'tags'               => $this->bookmark->tags->toStringCollection()->all()
            ]
        ];
    }
}
