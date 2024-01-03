<?php

declare(strict_types=1);

namespace App\Import;

final class Option
{
    private readonly array $options;

    public function __construct(array $data)
    {
        $default = [
            'merge_strategy'         => 'user_defined_tags_first',
            'bookmark_tags_exceeded' => 'slice',
            'include_bookmark_tags'  => true,
            'invalid_bookmark_tag'   => 'skip_tag',
            'tags_merge_overflow'    => '',
            'tags'                   => []
        ];

        $this->options = array_replace($default, $data);
    }

    public function tags(): array
    {
        return $this->options['tags'];
    }

    public function includeImportFileTags(): bool
    {
        return $this->options['include_bookmark_tags'];
    }

    public function skipBookmarkIfContainsAnyInvalidTag(): bool
    {
        return $this->options['invalid_bookmark_tag'] === 'skip_bookmark';
    }

    public function failImportIfBookmarkContainsAnyInvalidTag(): bool
    {
        return $this->options['invalid_bookmark_tag'] === 'fail_import';
    }

    public function skipBookmarkOnTagsMergeOverflow(): bool
    {
        return $this->options['tags_merge_overflow'] === 'skip_bookmark';
    }

    public function failImportOnTagsMergeOverflow(): bool
    {
        return $this->options['tags_merge_overflow'] === 'fail_import';
    }

    public function ignoreAllTagsOnTagsMergeOverflow(): bool
    {
        return $this->options['tags_merge_overflow'] === 'ignore_all_tags';
    }

    public function skipBookmarkIfTagsIsTooLarge(): bool
    {
        return $this->options['bookmark_tags_exceeded'] === 'skip_bookmark';
    }

    public function failImportIfBookmarkTagsIsTooLarge(): bool
    {
        return $this->options['bookmark_tags_exceeded'] === 'fail_import';
    }

    public function getMergeTagsStrategy(): MergeStrategy\MergeStrategy
    {
        return new MergeStrategy\MergeStrategy($this->options['merge_strategy']);
    }
}
