<?php

declare(strict_types=1);

namespace App\Importers\Safari;

use Traversable;
use App\Importers\DOMParser as AstractDOMParser;

final class DOMParser extends AstractDOMParser implements DOMParserInterface
{
    public function parse(string $html): Traversable
    {
        $this->setCollection($html, '//dt/a');

        return $this;
    }

    public function current(): Bookmark
    {
        /** @var \DOMElement */
        $DOMElement = $this->collection->current();

        return new Bookmark($DOMElement->getAttribute('href'));
    }
}
