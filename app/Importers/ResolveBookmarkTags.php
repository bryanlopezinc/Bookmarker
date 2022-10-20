<?php

declare(strict_types=1);

namespace App\Importers;

use App\Exceptions\InvalidTagException;
use App\ValueObjects\Tag;

trait ResolveBookmarkTags
{
    private function resolveTags(array $bookmarkTags, bool $ignoreTags): array
    {
        $tags = [];

        if ($ignoreTags) {
            return $tags;
        }

        if (count($bookmarkTags) > setting('MAX_BOOKMARKS_TAGS')) {
            return $tags;
        }

        foreach ($bookmarkTags as $tag) {
            if (!$this->tagIsCompatible($tag)) {
                continue;
            }

            $tags[] = $tag;
        }

        return collect($tags)
            ->uniqueStrict()
            ->values()
            ->all();
    }

    private function tagIsCompatible(string $tag): bool
    {
        try {
            new Tag($tag);
            return true;
        } catch (InvalidTagException) {
            return false;
        }
    }
}
