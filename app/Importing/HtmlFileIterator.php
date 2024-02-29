<?php

declare(strict_types=1);

namespace App\Importing;

use App\Importing\Enums\ImportSource;
use App\Importing\Collections\TagsCollection;
use DOMXPath;
use Iterator;
use App\Importing\DataTransferObjects\Bookmark;

final class HtmlFileIterator
{
    /**
     * @return Iterator<Bookmark>
     */
    public function iterate(string $html, ImportSource $importSource): Iterator
    {
        $generator = function () use ($html, $importSource) {
            $DOMNodeList = $this->getDOMXPath($html)->query($this->getXPathExpression($importSource));

            if ($DOMNodeList === false) {
                return yield from [];
            }

            /** @var \DOMElement $dOMElement*/
            foreach ($DOMNodeList as $dOMElement) {
                yield new Bookmark(
                    $dOMElement->getAttribute('href'),
                    new TagsCollection(explode(',', $dOMElement->getAttribute('tags'))),
                    $dOMElement->getLineNo()
                );
            }
        };

        return $generator();
    }

    private function getXPathExpression(ImportSource $importSource): string
    {
        return match ($importSource) {
            ImportSource::SAFARI,
            ImportSource::FIREFOX,
            ImportSource::CHROME => '//dt/a',
            ImportSource::POCKET,
            ImportSource::INSTAPAPER => '//li/a',
        };
    }

    private function getDOMXPath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $document->loadHTML($html);

        return new DOMXPath($document);
    }
}
