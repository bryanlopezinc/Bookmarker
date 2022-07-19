<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Closure;

final class UpdateBookmarkData
{
    public function __construct(public readonly Bookmark $bookmark)
    {
    }

    public function hasTitle(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->title);
    }

    public function hasResolvedUrl(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->resolvedUrl);
    }

    public function hasResolvedAt(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->resolvedAt);
    }

    public function hasCanonicalUrlHash(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->canonicalUrlHash);
    }

    public function hasCanonicalUrl(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->canonicalUrl);
    }

    public function hasDescription(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->description);
    }

    public function hasTags(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->tags);
    }

    public function hasThumbnailUrl(): bool
    {
        return $this->isInitialized(fn (Bookmark $b) => $b->thumbnailUrl);
    }

    private function isInitialized(Closure $attribute): bool
    {
        try {
            $attribute($this->bookmark);
            return true;
        } catch (\Throwable $e) {
            throw_unless(str_ends_with($e->getMessage(), 'must not be accessed before initialization'), $e);
            return false;
        }
    }
}
