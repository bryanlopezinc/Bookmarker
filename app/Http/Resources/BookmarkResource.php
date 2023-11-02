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
        return [
            'type'       => 'bookmark',
            'attributes' => [
                'id'                 => $this->bookmark->id,
                'title'              => $this->bookmark->title,
                'web_page_link'      => $this->bookmark->url,
                'has_preview_image'  => $this->bookmark->preview_image_url !== null,
                'preview_image_url'  => $this->when($this->bookmark->preview_image_url !== null, $this->bookmark->preview_image_url),
                'description'        => $this->when($this->bookmark->description !== null, $this->bookmark->description),
                'has_description'    => $this->bookmark->description !== null,
                'source'             => new SourceResource($this->bookmark->source),
                'tags'               => $this->when($this->bookmark->tags->isNotEmpty(), $this->bookmark->tags->pluck('name')->all()),
                'has_tags'           => $this->bookmark->tags->isNotEmpty(),
                'tags_count'         => $this->bookmark->tags->count(),
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
