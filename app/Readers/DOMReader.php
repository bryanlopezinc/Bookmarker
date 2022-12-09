<?php

declare(strict_types=1);

namespace App\Readers;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use DOMXPath;

class DOMReader
{
    private readonly DOMXPath $dOMXPath;
    private readonly Url $resolvedUrl;

    public function loadHTML(string $source, Url $resolvedUrl): self
    {
        libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $document->loadHTML($source);

        $this->dOMXPath = new DOMXPath($document);
        $this->resolvedUrl = $resolvedUrl;

        return $this;
    }

    public function getPageDescription(): string|false
    {
        $DOMNodeList =  $this->evaluate(
            '//meta[@property="og:description"]/@content',
            '//meta[@name="description"]/@content',
            '//meta[@name="twitter:description"]/@content'
        );

        if ($DOMNodeList === false) {
            return false;
        }

        return $this->filterValue($DOMNodeList->item(0)?->nodeValue);
    }

    /**
     * Evaluate the given Xpath Expressions (in order parsed) and return the DOMNodeList when any (or none) of the expression passes.
     * The return is false if the expression is malformed or the contextNode is invalid.
     */
    private function evaluate(string ...$expressions): \DOMNodeList|false
    {
        $DOMNodeList = false;

        foreach (func_get_args() as $expression) {
            $DOMNodeList = $this->dOMXPath->query($expression);

            if ($DOMNodeList !== false) {
                if ($DOMNodeList->count() > 0) {
                    break;
                }
            }
        }

        return $DOMNodeList;
    }

    public function getPreviewImageUrl(): Url|false
    {
        $DOMNodeList = $this->evaluate(
            '//meta[@property="og:image"]/@content',
            '//meta[@name="twitter:image"]/@content'
        );

        if ($DOMNodeList === false) {
            return false;
        }

        try {
            return new Url((string)$DOMNodeList->item(0)?->nodeValue);
        } catch (MalformedURLException) {
            return false;
        }
    }

    public function getPageTitle(): string|false
    {
        $DOMNodeList = $this->evaluate(
            '//meta[@property="og:title"]/@content',
            '/html/head/title',
            '//meta[@name="twitter:title"]/@content'
        );

        if ($DOMNodeList === false) {
            return false;
        }

        return $this->filterValue($DOMNodeList->item(0)?->nodeValue);
    }

    public function getSiteName(): string|false
    {
        $DOMNodeList = $this->evaluate(
            '//meta[@name="application-name"]/@content',
            '//meta[@property="og:site_name"]/@content',
            '//meta[@name="twitter:site"]/@content'
        );

        if ($DOMNodeList === false) {
            return false;
        }

        return $this->filterValue($DOMNodeList->item(0)?->nodeValue);
    }

    public function getCanonicalUrl(): Url|false
    {
        $DOMNodeList = $this->evaluate(
            '//link[@rel="canonical"]/@href',
            '//meta[@property="og:url"]/@content',
        );

        if ($DOMNodeList === false) {
            return false;
        }

        $value = $DOMNodeList->item(0)?->nodeValue;

        return (new ResolveCanonicalUrlValue((string) $value, $this->resolvedUrl))();
    }

    private function filterValue(?string $value): string|false
    {
        if (blank($value)) {
            return false;
        }

        //already null checked
        // @phpstan-ignore-next-line
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
