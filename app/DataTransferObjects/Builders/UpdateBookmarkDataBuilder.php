<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Builders;

use App\ValueObjects\UserID;
use App\ValueObjects\ResourceID;
use App\Collections\TagsCollection;
use App\ValueObjects\BookmarkTitle;
use App\ValueObjects\BookmarkDescription;
use App\Http\Requests\UpdateBookmarkRequest;
use App\DataTransferObjects\UpdateBookmarkData;

final class UpdateBookmarkDataBuilder extends Builder
{
    public static function fromRequest(UpdateBookmarkRequest $request): UpdateBookmarkDataBuilder
    {
        return static::new()
            ->id((int)$request->validated('id'))
            ->UserId(UserId::fromAuthUser()->toInt())
            ->tags($request->validated('tags', []))
            ->hasTitle($request->has('title'))
            ->hasDescription($request->has('description'))
            ->when($request->has('title'), fn (self $b) => $b->title($request->validated('title')))
            ->when($request->has('description'), fn (self $b) => $b->description($request->validated('description')));
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
        $this->attributes['tags'] = is_array($tags) ? TagsCollection::createFromStrings($tags) : $tags;

        return $this;
    }

    public function UserId(int $userId): self
    {
        $this->attributes['ownerId'] = new UserID($userId);

        return $this;
    }

    public function build(): UpdateBookmarkData
    {
        return new UpdateBookmarkData($this->attributes);
    }
}
