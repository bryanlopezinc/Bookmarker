<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Attributes\EnsureHasDatetimeformat;
use App\Attributes\EnsureValidTagsCount;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\Contracts\BelongsToUserInterface;
use App\Contracts\HashedUrlInterface;
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
    public readonly ResourceID $webPagesiteId;
    public readonly UserID $ownerId;
    public readonly Carbon $timeCreated;
    public readonly Carbon $timeUpdated;
    public readonly WebSite $fromWebSite;
    public readonly TagsCollection $tags;
    public bool $isHealthy;
    public bool $isUserFavourite;
    public readonly Url $canonicalUrl;
    public readonly HashedUrlInterface $canonicalUrlHash;
    public readonly Url $resolvedUrl;
    public readonly Carbon $resolvedAt;
    public readonly bool  $IsResolved;

    public function getOwnerID(): UserID
    {
        return $this->ownerId;
    }
}
