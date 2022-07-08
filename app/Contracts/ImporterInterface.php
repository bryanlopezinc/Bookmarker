<?php

declare(strict_types=1);

namespace App\Contracts;

interface ImporterInterface
{
    public function importBookmarks(array $source): void;
}
