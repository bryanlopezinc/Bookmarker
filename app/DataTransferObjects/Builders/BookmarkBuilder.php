<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\WebSite;
use App\Models\Bookmark as Model;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\WebPageDescription;
use App\ValueObjects\ResourceId;
use App\ValueObjects\TimeStamp;
use App\ValueObjects\Url;
use App\ValueObjects\UserId;

/**
 * Build a bookmarked webPage dataTransferObject
 */
final class BookmarkBuilder extends Builder
{
    public static function fromModel(Model $model): BookmarkBuilder
    {
        return (new BuildBookmarkFromModel)($model);
    }

    public static function new(): self
    {
        return new self;
    }

    public function id(int $id): self
    {
        $this->attributes['id'] = new ResourceId($id);

        return $this;
    }

    public function updatedAt(string $date): self
    {
        $this->attributes['timeUpdated'] = new TimeStamp($date);

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
        $this->attributes['linkToWebPage'] = new Url($url);

        return $this;
    }

    public function previewImageUrl(string $url): self
    {
        if (blank($url)) {
            $this->attributes['hasPreviewImageUrl'] = false;

            return $this;
        }

        $this->attributes['hasPreviewImageUrl'] = true;
        $this->attributes['previewImageUrl'] = new Url($url);

        return $this;
    }

    public function description(string $description): self
    {
        $this->attributes['description'] = new WebPageDescription($description);

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
        $this->attributes['tags'] = is_array($tags) ? TagsCollection::createFromStrings($tags) : $tags;

        return $this;
    }

    public function siteId(int $siteId): self
    {
        $this->attributes['webPagesiteId'] = new ResourceId($siteId);

        return $this;
    }

    public function bookmarkedById(int $userId): self
    {
        $this->attributes['ownerId'] = new UserId($userId);

        return $this;
    }

    public function site(WebSite $site): self
    {
        $this->attributes['fromWebSite'] = $site;

        return $this;
    }

    public function bookmarkedOn(string $date): self
    {
        $this->attributes['timeCreated'] = new TimeStamp($date);

        return $this;
    }

    public function build(): Bookmark
    {
        return new Bookmark($this->attributes);
    }
}
