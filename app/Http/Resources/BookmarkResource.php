<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Bookmark;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookmarkResource extends JsonResource
{
    public function __construct(private Bookmark $bookmark)
    {
        parent::__construct($bookmark);
    }

    public function toArray($request)
    {
        $tags = $this->bookmark->tags;
        $previewImageUrl = $this->bookmark->preview_image_url;
        $description = $this->bookmark->description;

        return [
            'type'       => 'bookmark',
            'attributes' => [
                'id'                 => $this->bookmark->public_id->present(),
                'title'              => $this->bookmark->title,
                'web_page_link'      => $this->bookmark->url,
                'has_preview_image'  => $this->bookmark->preview_image_url !== null,
                'preview_image_url'  => $this->when($previewImageUrl !== null, $previewImageUrl),
                'description'        => $this->when($description !== null, $description),
                'has_description'    => $this->bookmark->description !== null,
                'source'             => new SourceResource($this->bookmark->source),
                'tags'               => $this->when($tags->isNotEmpty(), $tags->pluck('name')->all()),
                'has_tags'           => $tags->isNotEmpty(),
                'tags_count'         => $tags->count(),
                'is_healthy'         => $this->bookmark->isHealthy,
                'is_user_favorite'   => $this->bookmark->isUserFavorite,
                'has_duplicates'     => $this->bookmark->hasDuplicates,
                'created_on'         => [
                    'date_readable'  => $this->bookmark->created_at->diffForHumans(),
                    'date_time'      => $this->bookmark->created_at->toDateTimeString(),
                    'date'           => $this->bookmark->created_at->toDateString(),
                ]
            ]
        ];
    }
}
