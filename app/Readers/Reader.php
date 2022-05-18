<?php

declare(strict_types=1);

namespace App\Readers;

use App\ValueObjects\Url;
use DOMXPath;

final class Reader
{
    private readonly DOMXPath $dOMXPath;

    public function __construct(string $source)
    {
        libxml_use_internal_errors(true);

        $documnet = new \DOMDocument();
        $documnet->loadHTML($source);

        $this->dOMXPath = new DOMXPath($documnet);
    }

    public function getPageDescription(): string|false
    {
        $description = $this->evalute(
            '//meta[@property="og:description"]/@content',
            '//meta[@name="description"]/@content'
        )->item(0)?->nodeValue;

        return $this->filterValue($description);
    }

    /**
     * Evaluate the given Xpath Expressions (in order parsed) and return the DOMNodeList when any (or none) expression passes.
     * The return is false if the expression is malformed or the contextnode is invalid.
     */
    private function evalute(string ...$expressions): \DOMNodeList|false
    {
        $DOMNodeList = false;

        foreach (func_get_args() as $expression) {
            $DOMNodeList = $this->dOMXPath->query($expression);

            if ($DOMNodeList->count() > 0) {
                break;
            }
        }

        return $DOMNodeList;
    }

    public function getPreviewImageUrl(): Url|false
    {
        return Url::tryFromString(
            $this->evalute('//meta[@property="og:image"]/@content')->item(0)?->nodeValue
        );
    }

    public function getPageTitle(): string|false
    {
        $title = $this->evalute(
            '//meta[@property="og:title"]/@content',
            '/html/head/title'
        )->item(0)?->nodeValue;

        return $this->filterValue($title);
    }

    public function getSiteName(): string|false
    {
        $name = $this->evalute(
            '//meta[@name="application-name"]/@content',
            '//meta[@property="og:site_name"]/@content'
        )->item(0)?->nodeValue;

        return $this->filterValue($name);
    }

    private function filterValue(?string $value): string|false
    {
        return blank($value) ? false : htmlspecialchars($value, ENT_QUOTES);
    }
}
