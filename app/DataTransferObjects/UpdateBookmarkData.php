<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\UserId;
use App\ValueObjects\ResourceId;
use App\Collections\TagsCollection;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\WebPageDescription;

final class UpdateBookmarkData extends DataTransferObject
{
    public readonly ResourceId $id;
    public readonly BookmarkTitle $title;
    public readonly bool $hasTitle;
    public readonly WebPageDescription $description;
    public readonly bool $hasDescription;
    public readonly UserId $ownerId;
    public readonly TagsCollection $tags;
}
