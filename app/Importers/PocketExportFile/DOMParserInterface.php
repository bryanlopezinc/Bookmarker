<?php

declare(strict_types=1);

namespace App\Importers\PocketExportFile;

use Traversable;

interface DOMParserInterface
{
    /**
     * @return Traversable<PocketBookmark>
     */
    public function parse(string $html): Traversable;
}
