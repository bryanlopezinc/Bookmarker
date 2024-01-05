<?php

declare(strict_types=1);

namespace App\Import;

use Exception;

enum BookmarkImportStatus: int
{
    case SUCCESS                            = 3;
    case FAILED_DUE_TO_INVALID_TAG          = 5;
    case FAILED_DUE_TO_MERGE_TAGS_EXCEEDED  = 6;
    case FAILED_DUE_TO_SYSTEM_ERROR         = 7;
    case FAILED_DUE_TO_INVALID_URL          = 8;
    case FAILED_DUE_TO_TOO_MANY_TAGS        = 9;
    case SKIPPED_DUE_TO_INVALID_TAG         = 10;
    case SKIPPED_DUE_TO_MERGE_TAGS_EXCEEDED = 11;
    case SKIPPED_DUE_TO_TOO_MANY_TAGS       = 12;

    public static function fromSkippedOutcome(ReasonForSkippingBookmark $reason): self
    {
        return match ($reason) {
            ReasonForSkippingBookmark::INVALID_TAG => self::SKIPPED_DUE_TO_INVALID_TAG,
            ReasonForSkippingBookmark::TAG_MERGE_OVERFLOW => self::SKIPPED_DUE_TO_MERGE_TAGS_EXCEEDED,
            ReasonForSkippingBookmark::TAGS_TOO_LARGE => self::SKIPPED_DUE_TO_TOO_MANY_TAGS
        };
    }

    public static function fromFailedOutcome(ImportBookmarksStatus $reason): self
    {
        return match ($reason) {
            ImportBookmarksStatus::FAILED_DUE_TO_INVALID_TAG => self::FAILED_DUE_TO_INVALID_TAG,
            ImportBookmarksStatus::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED => self::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED,
            ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR => self::FAILED_DUE_TO_SYSTEM_ERROR,
            ImportBookmarksStatus::FAILED_DUE_TO_INVALID_BOOKMARK_URL => self::FAILED_DUE_TO_INVALID_URL,
            ImportBookmarksStatus::FAILED_DUE_TO_TO_MANY_TAGS => self::FAILED_DUE_TO_TOO_MANY_TAGS,
            default => throw new Exception('Status is not failed.')
        };
    }

    /**
     * @return array<int>
     */
    public static function failedCases(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status) => str_starts_with($status->name, 'FAILED'))
            ->map(fn (self $status) => $status->value)
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function skippedCases(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status) => str_starts_with($status->name, 'SKIPPED'))
            ->map(fn (self $status) => $status->value)
            ->values()
            ->all();
    }

    public function isSuccessful(): bool
    {
        return $this == self::SUCCESS;
    }

    public function toWord(): string
    {
        return match ($this) {
            self::SUCCESS                            => 'Successful',
            self::FAILED_DUE_TO_INVALID_TAG          => 'FailedDueToInvalidTag',
            self::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED  => 'FailedDueToTagsMergeConflict',
            self::FAILED_DUE_TO_SYSTEM_ERROR         => 'FailedDueToSystemError',
            self::FAILED_DUE_TO_INVALID_URL          => 'FailedDueToInvalidUrl',
            self::FAILED_DUE_TO_TOO_MANY_TAGS        => 'FailedDueToTooManyTags',
            self::SKIPPED_DUE_TO_INVALID_TAG         => 'SkippedDueToInvalidTag',
            self::SKIPPED_DUE_TO_MERGE_TAGS_EXCEEDED => 'SkippedDueToTagsMergeConflict',
            self::SKIPPED_DUE_TO_TOO_MANY_TAGS       => 'SkippedDueToTooMAnyTags'
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::FAILED_DUE_TO_INVALID_TAG,
            self::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED,
            self::FAILED_DUE_TO_SYSTEM_ERROR,
            self::FAILED_DUE_TO_INVALID_URL,
            self::FAILED_DUE_TO_TOO_MANY_TAGS => 'failed',
            self::SKIPPED_DUE_TO_INVALID_TAG,
            self::SKIPPED_DUE_TO_MERGE_TAGS_EXCEEDED,
            self::SKIPPED_DUE_TO_TOO_MANY_TAGS => 'skipped'
        };
    }
}
