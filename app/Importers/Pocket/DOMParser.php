<?php

declare(strict_types=1);

namespace App\Importers\Pocket;

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
            foreach ($this->getDOMXPath($html)->query('//li/a')->getIterator() as $dOMElement) {
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

    public function current(): Bookmark
    {
        /** @var \DOMElement */
        $DOMElement = $this->collection->current();

        return new Bookmark(
            $DOMElement->getAttribute('href'),
            $DOMElement->getAttribute('time_added'),
            explode(',', $DOMElement->getAttribute('tags')),
        );
    }
}
