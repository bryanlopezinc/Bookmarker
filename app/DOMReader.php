<?php

declare(strict_types=1);

namespace App;

use App\ValueObjects\Url;
use DOMXPath;

final class DOMReader
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
        $description =  $this->dOMXPath->query('//meta[@name="og:description"]/@content')->item(0)?->nodeValue;

        if (!$description) {
            $description = $this->dOMXPath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue;
        }

        return $this->filterValue($description);
    }

    public function getPreviewImageUrl(): Url|false
    {
        return Url::tryFromString(
            $this->dOMXPath->query('//meta[@name="og:image"]/@content')->item(0)?->nodeValue
        );
    }

    public function getPageTitle(): string|false
    {
        $title = $this->dOMXPath->query('//meta[@name="og:title"]/@content')->item(0)?->nodeValue;

        if (!$title) {
            $title = $this->dOMXPath->query('/html/head/title')->item(0)?->nodeValue;
        }

        return $this->filterValue($title);
    }

    public function getSiteName(): string|false
    {
        $name = $this->dOMXPath->query('//meta[@name="og:site_name"]/@content')->item(0)?->nodeValue;

        if (!$name) {
            $name = $this->dOMXPath->query('//meta[@name="application-name"]/@content')->item(0)?->nodeValue;
        }

        return $this->filterValue($name);
    }

    private function filterValue(?string $value): string|false
    {
        return is_null($value) ? false : htmlspecialchars($value, ENT_QUOTES);
    }
}
