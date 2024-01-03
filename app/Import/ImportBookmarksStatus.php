<?php

declare(strict_types=1);

namespace App\Import;

use LogicException;

enum ImportBookmarksStatus: int
{
    case PENDING                            = 1;
    case IMPORTING                          = 3;
    case SUCCESS                            = 4;
    case FAILED_DUE_TO_INVALID_TAG          = 5;
    case FAILED_DUE_TO_MERGE_TAGS_EXCEEDED  = 6;
    case FAILED_DUE_TO_SYSTEM_ERROR         = 7;
    case FAILED_DUE_TO_INVALID_BOOKMARK_URL = 8;
    case FAILED_DUE_TO_TO_MANY_TAGS         = 9;

    public function isRunning(): bool
    {
        return $this == self::IMPORTING;
    }

    public function isSuccessful(): bool
    {
        return $this == ImportBookmarksStatus::SUCCESS;
    }

    public function failed(): bool
    {
        return str_starts_with($this->name, 'FAILED');
    }

    public function category(): string
    {
        return match ($this) {
            self::PENDING   => 'pending',
            self::SUCCESS   => 'success',
            self::IMPORTING => 'importing',
            default         => 'failed',
        };
    }

    public function reason(): string
    {
        return match ($this) {
            self::FAILED_DUE_TO_INVALID_TAG          => 'FailedDueToInvalidTag',
            self::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED  => 'FailedDueToTagsMergeConflict',
            self::FAILED_DUE_TO_SYSTEM_ERROR         => 'FailedDueToSystemError',
            self::FAILED_DUE_TO_INVALID_BOOKMARK_URL => 'FailedDueToInvalidUrl',
            self::FAILED_DUE_TO_TO_MANY_TAGS         => 'FailedDueToTooManyTags',
            default => throw new LogicException('only a failed outcome can have a reason')
        };
    }
}
