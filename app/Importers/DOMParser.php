<?php

declare(strict_types=1);

namespace App\Importers;

use DOMXPath;
use Generator;
use Iterator;

abstract class DOMParser implements Iterator
{
    protected  Generator $collection;

    protected function setCollection(string $html, string $XPathExpression): void
    {
        $generator = function () use ($html, $XPathExpression) {
            foreach ($this->getDOMXPath($html)->query($XPathExpression)->getIterator() as $dOMElement) {
                yield $dOMElement;
            }
        };

        $this->collection = $generator();
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
}
