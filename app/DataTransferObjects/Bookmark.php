<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Attributes\EnsureValidBookmark;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use App\ValueObjects\TimeStamp;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\Contracts\BelongsToUserInterface;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\BookmarkDescription;

#[EnsureValidBookmark]
final class Bookmark extends DataTransferObject implements BelongsToUserInterface
{
    public readonly ResourceID $id;
    public readonly BookmarkTitle $title;
    public readonly bool $hasCustomTitle;
    public readonly Url $linkToWebPage;
    public readonly Url $previewImageUrl;
    public readonly bool $hasPreviewImageUrl;
    public readonly BookmarkDescription $description;
    public readonly bool $descriptionWasSetByUser;
    public readonly ResourceID $webPagesiteId;
    public readonly UserID $ownerId;
    public readonly TimeStamp $timeCreated;
    public readonly TimeStamp $timeUpdated;
    public readonly WebSite $fromWebSite;
    public readonly TagsCollection $tags;
    public bool $isHealthy;
    public bool $isUserFavourite;

    public function getOwnerID(): UserID
    {
        return $this->ownerId;
    }
}
