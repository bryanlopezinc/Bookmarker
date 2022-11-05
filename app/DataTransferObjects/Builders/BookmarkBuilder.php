<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Source;
use App\HashedUrl;
use App\Models\Bookmark as Model;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\BookmarkDescription;
use App\ValueObjects\ResourceID;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use Carbon\Carbon;

final class BookmarkBuilder extends Builder
{
    public static function fromModel(Model $model): BookmarkBuilder
    {
        return (new BuildBookmarkFromModel)($model);
    }

    public static function fromBookmark(Bookmark $bookmark): BookmarkBuilder
    {
        return new self($bookmark->toArray());
    }

    public static function new(): self
    {
        return new self;
    }

    public function id(int $id): self
    {
        $this->attributes['id'] = new ResourceID($id);

        return $this;
    }

    public function updatedAt(string $date): self
    {
        $this->attributes['timeUpdated'] = Carbon::parse($date);

        return $this;
    }

    public function title(string $title): self
    {
        $this->attributes['title'] = new BookmarkTitle($title);

        return $this;
    }

    public function hasCustomTitle(bool $hasCustomTitle): self
    {
        $this->attributes['hasCustomTitle'] = $hasCustomTitle;

        return $this;
    }

    public function url(string $url): self
    {
        $this->attributes['url'] = new Url($url);

        return $this;
    }

    public function thumbnailUrl(string|Url $url): self
    {
        if ($url instanceof Url) {
            $this->attributes['hasThumbnailUrl'] = true;
            $this->attributes['thumbnailUrl'] = $url;

            return $this;
        }

        $this->attributes['hasThumbnailUrl'] = filled($url);

        if (blank($url)) {
            return $this;
        }

        $this->attributes['thumbnailUrl'] = new Url($url);

        return $this;
    }

    public function description(?string $description): self
    {
        $this->attributes['description'] = new BookmarkDescription($description);

        return $this;
    }

    public function descriptionWasSetByUser(bool $wasSetByUser): self
    {
        $this->attributes['descriptionWasSetByUser'] = $wasSetByUser;

        return $this;
    }

    /**
     * @param TagsCollection|array<string> $tags
     */
    public function tags(TagsCollection|array $tags): self
    {
        $this->attributes['tags'] = is_array($tags) ? TagsCollection::make($tags) : $tags;

        return $this;
    }

    public function sourceID(int $sourceID): self
    {
        $this->attributes['sourceID'] = new ResourceID($sourceID);

        return $this;
    }

    public function bookmarkedById(int $userId): self
    {
        $this->attributes['ownerId'] = new UserID($userId);

        return $this;
    }

    public function source(Source $source): self
    {
        $this->attributes['source'] = $source;

        return $this;
    }

    public function bookmarkedOn(string $date): self
    {
        $this->attributes['timeCreated'] = Carbon::parse($date);

        return $this;
    }

    public function isHealthy(bool $value): self
    {
        $this->attributes['isHealthy'] = $value;

        return $this;
    }

    public function isUserFavorite(bool $value): self
    {
        $this->attributes['isUserFavorite'] = $value;

        return $this;
    }

    public function canonicalUrl(Url|string $url): self
    {
        $this->attributes['canonicalUrl'] = is_string($url) ? new Url($url) : $url;

        return $this;
    }

    public function resolvedUrl(Url|string $url): self
    {
        $this->attributes['resolvedUrl'] = is_string($url) ? new Url($url) : $url;

        return $this;
    }

    public function resolvedAt(?Carbon $date): self
    {
        if ($date === null) {
            $this->IsResolved(false);
            return $this;
        }

        $this->IsResolved(true);
        $this->attributes['resolvedAt'] = $date;

        return $this;
    }

    public function IsResolved(bool $IsResolved): self
    {
        $this->attributes['IsResolved'] = $IsResolved;

        return $this;
    }

    public function canonicalUrlHash(HashedUrl|string $hash): self
    {
        $this->attributes['canonicalUrlHash'] = is_string($hash) ? new HashedUrl($hash) : $hash;

        return $this;
    }

    public function build(): Bookmark
    {
        return new Bookmark($this->attributes);
    }
}
