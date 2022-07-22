<?php

declare(strict_types=1);

namespace App\Importers\Chrome;

use App\Importers\DOMParser as AstractDOMParser;
use Traversable;

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

        return new Bookmark(
            $DOMElement->getAttribute('href'),
            $DOMElement->getAttribute('add_date'),
        );
    }
}
