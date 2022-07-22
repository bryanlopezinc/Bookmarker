<?php

declare(strict_types=1);

namespace App\Importers\Safari;

final class Bookmark
{
    public function __construct(public readonly string $url)
    {
    }
}