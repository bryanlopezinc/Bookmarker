<?php

declare(strict_types=1);

namespace App\Importing\Enums;

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

    /**
     * @return array<int>
     */
    public static function failedCases(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status) => $status->failed())
            ->map(fn (self $status) => $status->value)
            ->values()
            ->all();
    }

    public static function fromRequest(string $category): self
    {
        return match ($category) {
            'pending' => self::PENDING,
            'success' => self::SUCCESS,
            default   => self::IMPORTING,
        };
    }

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

    public function toNotificationMessage(): string
    {
        return match ($this) {
            self::FAILED_DUE_TO_INVALID_TAG          => 'Import could not be completed because an invalid tag was found.',
            self::FAILED_DUE_TO_MERGE_TAGS_EXCEEDED  => 'Import could not be completed because an tags could not be merged.',
            self::FAILED_DUE_TO_SYSTEM_ERROR         => 'Import could not be completed due to a system error.',
            self::FAILED_DUE_TO_INVALID_BOOKMARK_URL => 'Import could not be completed because an invalid bookmark was found.',
            self::FAILED_DUE_TO_TO_MANY_TAGS         => 'Import could not be completed because bookmark with too many tags was encountered.',
            default => throw new LogicException('only a failed outcome can have a reason')
        };
    }
}
