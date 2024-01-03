<?php

declare(strict_types=1);

namespace App\Import;

use LogicException;

final class ImportBookmarksOutcome
{
    public function __construct(
        public readonly ImportBookmarksStatus $status,
        public readonly ImportStats $statistics,
    ) {
    }

    public static function success(ImportStats $stats): self
    {
        return new self(ImportBookmarksStatus::SUCCESS, $stats);
    }

    public static function failed(ImportBookmarksStatus $status, ImportStats $stats): self
    {
        if (!$status->failed()) {
            throw new LogicException('Outcome is not failed.');
        }

        return new self($status, $stats);
    }
}
