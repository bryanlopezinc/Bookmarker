<?php

declare(strict_types=1);

namespace App\Importing\Enums;

use Exception;

enum BookmarkImportStatus: int
{
        // Each category of should follow a
        // numerical sequence for easy filtering
        // Eg `where status Between 2 and 6` will fetch all cases within that category.
    case SUCCESS                            = 1;

        //failed
    case FAILED_DUE_TO_INVALID_TAG          = 101;
    case FAILED_DUE_TO_MERGE_TAGS_EXCEEDED  = 102;
    case FAILED_DUE_TO_SYSTEM_ERROR         = 103;
    case FAILED_DUE_TO_INVALID_URL          = 104;
    case FAILED_DUE_TO_TOO_MANY_TAGS        = 105;

        //skipped
    case SKIPPED_DUE_TO_INVALID_TAG         = 201;
    case SKIPPED_DUE_TO_MERGE_TAGS_EXCEEDED = 202;
    case SKIPPED_DUE_TO_TOO_MANY_TAGS       = 203;

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
        return range(101, 105);
    }

    /**
     * @return array<int>
     */
    public static function skippedCases(): array
    {
        return range(201, 203);
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
