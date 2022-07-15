<?php

declare(strict_types=1);

namespace App\Importers\Concerns;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

trait ResolvesImportTimestamp
{
    private function resolveImportTimestamp(bool $useTimestamp, string $timestamp): string
    {
        $default = (string) now();

        if ($useTimestamp === false || blank($timestamp)) {
            return $default;
        }

        try {
            return (string) Carbon::createFromTimestamp($timestamp);
        } catch (InvalidFormatException) {
            return $default;
        }
    }
}
