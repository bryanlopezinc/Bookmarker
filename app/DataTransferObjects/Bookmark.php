<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Attributes\EnsureValidTagsCount;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\Contracts\BelongsToUserInterface;
use App\HashedUrl;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\BookmarkDescription;
use Carbon\Carbon;

#[EnsureValidTagsCount('MAX_BOOKMARKS_TAGS', 'tags')]
final class Bookmark extends DataTransferObject implements BelongsToUserInterface
{
    public readonly ResourceID $id;
    public readonly BookmarkTitle $title;
    public readonly bool $hasCustomTitle;
    public readonly Url $url;
    public readonly Url $thumbnailUrl;
    public readonly bool $hasThumbnailUrl;
    public readonly BookmarkDescription $description;
    public readonly bool $descriptionWasSetByUser;
    public readonly ResourceID $sourceID;
    public readonly UserID $ownerId;
    public readonly Carbon $timeCreated;
    public readonly Carbon $timeUpdated;
    public readonly Source $source;
    public readonly TagsCollection $tags;
    public bool $isHealthy;
    public bool $isUserFavorite;
    public readonly Url $canonicalUrl;
    public readonly HashedUrl $canonicalUrlHash;
    public readonly Url $resolvedUrl;
    public readonly Carbon $resolvedAt;
    public readonly bool  $IsResolved;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(protected array $attributes)
    {
        foreach ($this->attributes as $key => $value) {
            $this->{$key} = $value;
        }

        parent::__construct();
    }

    public function getOwnerID(): UserID
    {
        return $this->ownerId;
    }
}
