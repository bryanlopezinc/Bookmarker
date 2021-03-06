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
            'attributes' => [
                'id' => $this->bookmark->id->toInt(),
                'title' => $this->bookmark->title->safe(),
                'web_page_link' => $this->bookmark->url->toString(),
                'has_preview_image'  => $this->bookmark->hasThumbnailUrl,
                'preview_image_url'  => $this->when($this->bookmark->hasThumbnailUrl, fn () => $this->bookmark->thumbnailUrl->toString()),
                'description' => $this->when(!$this->bookmark->description->isEmpty(), fn () => $this->bookmark->description->safe()),
                'has_description' => !$this->bookmark->description->isEmpty(),
                'from_site' => new WebSiteResource($this->bookmark->fromWebSite),
                'tags' => $this->bookmark->tags->toStringCollection()->all(),
                'has_tags' => $this->bookmark->tags->isNotEmpty(),
                'tags_count' => $this->bookmark->tags->count(),
                'is_healthy' => $this->bookmark->isHealthy,
                'is_user_favourite' => $this->bookmark->isUserFavourite,
                'created_on' => [
                    'date_readable' => $this->bookmark->timeCreated->diffForHumans(),
                    'date_time' => $this->bookmark->timeCreated->toDateTimeString(),
                    'date' => $this->bookmark->timeCreated->toDateString(),
                ]
            ]
        ];
    }
}
