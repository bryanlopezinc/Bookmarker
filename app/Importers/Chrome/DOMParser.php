<?php

declare(strict_types=1);

namespace App\Importers\Chrome;

use DOMXPath;
use Generator;
use Iterator;
use Traversable;

final class DOMParser implements Iterator, DOMParserInterface
{
    private Generator $collection;

    public function parse(string $html): Traversable
    {
        $generator = function () use ($html) {
            foreach ($this->getDOMXPath($html)->query('//dt/a')->getIterator() as $dOMElement) {
                yield $dOMElement;
            }
        };

        $this->collection = $generator();

        return $this;
    }

    private function getDOMXPath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);

        $documnet = new \DOMDocument();
        $documnet->loadHTML($html);

        return new DOMXPath($documnet);
    }

    public function rewind(): void
    {
        $this->collection->rewind();
    }

    public function valid(): bool
    {
        return $this->collection->valid();
    }

    public function next(): void
    {
        $this->collection->next();
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->collection->key();
    }

    public function current(): ChromeBookmark
    {
        /** @var \DOMElement */
        $DOMElement = $this->collection->current();

        return new ChromeBookmark(
            $DOMElement->getAttribute('href'),
            $DOMElement->getAttribute('add_date'),
        );
    }
}
