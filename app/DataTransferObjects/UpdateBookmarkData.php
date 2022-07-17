<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\ValueObjects\UserID;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\Contracts\HashedUrlInterface;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\BookmarkDescription;
use App\ValueObjects\Url;

final class UpdateBookmarkData extends DataTransferObject
{
    public readonly ResourceID $id;
    public readonly BookmarkTitle $title;
    public readonly bool $hasTitle;
    public readonly BookmarkDescription $description;
    public readonly bool $hasDescription;
    public readonly TagsCollection $tags;
    public readonly bool $hasPreviewImageUrl;
    public readonly Url $previewImageUrl;
    public readonly Url $canonicalUrl;
    public readonly bool $hasCanonicalUrl;
    public readonly bool $hasCanonicalUrlHash;
    public readonly HashedUrlInterface $canonicalUrlHash;
    public readonly Url $resolvedUrl;
    public readonly bool $hasResolvedUrl;
}
