<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\Contracts\HashedUrlInterface;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\BookmarkDescription;
use App\Http\Requests\UpdateBookmarkRequest;
use App\DataTransferObjects\UpdateBookmarkData;
use App\ValueObjects\Url;

final class UpdateBookmarkDataBuilder extends Builder
{
    public static function fromRequest(UpdateBookmarkRequest $request): UpdateBookmarkDataBuilder
    {
        return static::new()
            ->id((int)$request->validated('id'))
            ->tags($request->validated('tags', []))
            ->hasTitle($request->has('title'))
            ->hasDescription($request->has('description'))
            ->when($request->has('title'), fn (self $b) => $b->title($request->validated('title')))
            ->when($request->has('description'), fn (self $b) => $b->description($request->validated('description')));
    }

    public static function new(): self
    {
        return (new self)
            ->hasTitle(false)
            ->hasDescription(false)
            ->hasPreviewImageUrl(false)
            ->hasResolvedUrl(false)
            ->hasCanonicalUrl(false)
            ->hasCanonicalUrlHash(false)
            ->hasResolvedUrl(false)
            ->tags([]);
    }

    public function id(int $id): self
    {
        $this->attributes['id'] = new ResourceID($id);

        return $this;
    }

    public function previewImageUrl(Url $url): self
    {
        $this->attributes['previewImageUrl'] = $url;
        $this->hasPreviewImageUrl(true);

        return $this;
    }

    public function hasPreviewImageUrl(bool $hasPreviewImageUrl): self
    {
        $this->attributes['hasPreviewImageUrl'] = $hasPreviewImageUrl;

        return $this;
    }

    public function title(string $title): self
    {
        $this->attributes['title'] = new BookmarkTitle($title);

        $this->hasTitle(true);

        return $this;
    }

    public function hasTitle(bool $hasTitle): self
    {
        $this->attributes['hasTitle'] = $hasTitle;

        return $this;
    }

    public function description(string $description): self
    {
        $this->attributes['description'] = new BookmarkDescription($description);

        $this->hasDescription(true);

        return $this;
    }

    public function hasDescription(bool $hasDescription): self
    {
        $this->attributes['hasDescription'] = $hasDescription;

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

    public function resolvedUrl(Url $url): self
    {
        $this->hasResolvedUrl();
        $this->attributes['resolvedUrl'] = $url;

        return $this;
    }

    public function hasResolvedUrl(bool $hasResolvedUrl = true): self
    {
        $this->attributes['hasResolvedUrl'] = $hasResolvedUrl;

        return $this;
    }

    public function canonicalUrl(Url $url): self
    {
        $this->hasCanonicalUrl();
        $this->attributes['canonicalUrl'] = $url;

        return $this;
    }

    public function hasCanonicalUrl(bool $hasCanonicalUrl = true): self
    {
        $this->attributes['hasCanonicalUrl'] = $hasCanonicalUrl;

        return $this;
    }

    public function canonicalUrlHash(HashedUrlInterface $hash): self
    {
        $this->hasCanonicalUrlHash();
        $this->attributes['canonicalUrlHash'] =  $hash;

        return $this;
    }

    public function hasCanonicalUrlHash(bool $hasCanonicalUrlHash = true): self
    {
        $this->attributes['hasCanonicalUrlHash'] = $hasCanonicalUrlHash;

        return $this;
    }

    public function build(): UpdateBookmarkData
    {
        return new UpdateBookmarkData($this->attributes);
    }
}
