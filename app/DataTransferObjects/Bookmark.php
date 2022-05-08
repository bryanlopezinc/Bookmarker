<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\Url;
use App\ValueObjects\UserId;
use App\ValueObjects\TimeStamp;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\WebPageDescription;

final class Bookmark extends DataTransferObject
{
    public readonly ResourceID $id;
    public readonly BookmarkTitle $title;
    public readonly bool $hasCustomTitle;
    public readonly Url $linkToWebPage;
    public readonly Url $previewImageUrl;
    public readonly bool $hasPreviewImageUrl;
    public readonly WebPageDescription $description;
    public readonly bool $descriptionWasSetByUser;
    public readonly ResourceID $webPagesiteId;
    public readonly UserId $ownerId;
    public readonly TimeStamp $timeCreated;
    public readonly TimeStamp $timeupdated;
    public readonly WebSite $fromWebSite;
    public readonly TagsCollection $tags;
}
