<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\UserID;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\WebPageDescription;

final class UpdateBookmarkData extends DataTransferObject
{
    public readonly ResourceID $id;
    public readonly BookmarkTitle $title;
    public readonly bool $hasTitle;
    public readonly WebPageDescription $description;
    public readonly bool $hasDescription;
    public readonly UserID $ownerId;
    public readonly TagsCollection $tags;
}
