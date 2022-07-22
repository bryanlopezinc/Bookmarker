<?php

declare(strict_types=1);

namespace App\Importers\Safari;

use Traversable;

interface DOMParserInterface
{
    /**
     * @return Traversable<Bookmark>
     */
    public function parse(string $html): Traversable;
}
