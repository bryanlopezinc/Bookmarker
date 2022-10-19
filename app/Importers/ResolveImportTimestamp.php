<?php

declare(strict_types=1);

namespace App\Importers;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

trait ResolveImportTimestamp
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
