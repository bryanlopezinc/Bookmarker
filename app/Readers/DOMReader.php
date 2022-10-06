<?php

declare(strict_types=1);

namespace App\Readers;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use DOMXPath;

final class DOMReader
{
    private readonly DOMXPath $dOMXPath;
    private readonly Url $resolvedUrl;

    public function __construct(string $source, Url $resolvedUrl)
    {
        libxml_use_internal_errors(true);

        $documnet = new \DOMDocument();
        $documnet->loadHTML($source);

        $this->dOMXPath = new DOMXPath($documnet);
        $this->resolvedUrl = $resolvedUrl;
    }

    public function getPageDescription(): string|false
    {
        $DOMNodeList =  $this->evalute(
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
     * The return is false if the expression is malformed or the contextnode is invalid.
     */
    private function evalute(string ...$expressions): \DOMNodeList|false
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
        $DOMNodeList = $this->evalute(
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
        $DOMNodeList = $this->evalute(
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
        $DOMNodeList = $this->evalute(
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
        $DOMNodeList = $this->evalute(
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
        return blank($value) ? false : htmlspecialchars($value, ENT_QUOTES);
    }
}
