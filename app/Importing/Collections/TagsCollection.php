<?php

declare(strict_types=1);

namespace App\Importing\Collections;

use App\Rules\TagRule;
use Illuminate\Support\Collection;

final class TagsCollection
{
    private readonly Collection $tags;

    public function __construct(array $tags)
    {
        $this->tags = collect($tags)->filter()->uniqueStrict();
    }

    public function hasInvalid(): bool
    {
        return filled($this->invalid());
    }

    public function invalid(): array
    {
        $rule = new TagRule();

        return $this->tags->filter(fn (string $tag) => !$rule->passes('', $tag))->all();
    }

    public function valid(): Collection
    {
        $rule = new TagRule();

        return $this->tags->filter(fn (string $tag) => $rule->passes('', $tag));
    }

    public function willOverflowWhenMergedWithUserDefinedTags(array $tags): bool
    {
        return $this->valid()->merge($tags)->count() > setting('MAX_BOOKMARK_TAGS');
    }

    public function all(): array
    {
        return $this->tags->all();
    }
}
