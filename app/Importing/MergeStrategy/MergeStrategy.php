<?php

declare(strict_types=1);

namespace App\Importing\MergeStrategy;

use Illuminate\Support\Collection;

final class MergeStrategy
{
    public function __construct(private readonly string $strategy)
    {
    }

    public function merge(Collection $userDefinedTags, Collection $bookmarkTags): Collection
    {
        $maxBookmarksTags = setting('MAX_BOOKMARK_TAGS');

        return match ($this->strategy) {
            'ignore_all'              => collect(),
            'user_defined_tags_first' => $userDefinedTags->merge($bookmarkTags->take($maxBookmarksTags - $userDefinedTags->count())),
            'import_file_tags_first'  => $this->mergeUsingImportFileTagsFirst($userDefinedTags, $bookmarkTags)
        };
    }

    private function mergeUsingImportFileTagsFirst(Collection $userDefinedTags, Collection $bookmarkTags): Collection
    {
        $maxBookmarksTags = setting('MAX_BOOKMARK_TAGS');

        $bookmarkTags = $bookmarkTags->take($maxBookmarksTags);

        return $bookmarkTags->merge($userDefinedTags->take($maxBookmarksTags - $bookmarkTags->count()));
    }
}
