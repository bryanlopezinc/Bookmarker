<?php

declare(strict_types=1);

namespace App\Importers;

use App\Exceptions\InvalidTagException;
use App\Rules\TagRule;

trait ResolveBookmarkTags
{
    private function resolveTags(array $bookmarkTags, bool $ignoreTags): array
    {
        $tags = [];

        if ($ignoreTags) {
            return $tags;
        }

        if (count($bookmarkTags) > 15) {
            return $tags;
        }

        foreach ($bookmarkTags as $tag) {
            if (!$this->tagIsCompatible($tag)) {
                continue;
            }

            $tags[] = $tag;
        }

        return collect($tags)
            ->map(fn (string $tag) => mb_strtolower($tag))
            ->uniqueStrict()
            ->values()
            ->all();
    }

    private function tagIsCompatible(string $tag): bool
    {
        try {
            return (new TagRule())->passes('', $tag);
        } catch (InvalidTagException) {
            return false;
        }
    }
}
