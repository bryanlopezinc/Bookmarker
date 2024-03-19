<?php

declare(strict_types=1);

namespace App\Readers;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use Spatie\Url\Url as SpatieUrl;

final class ResolveCanonicalUrlValue
{
    public function __construct(private string $canonicalUrl, private Url $resolvedUrl)
    {
    }

    public function __invoke(): Url|false
    {
        if ($this->isRelativeUrl()) {
            return $this->removeQueryParametersFromResolvedUrl();
        }

        try {
            $canonicalUrl = new Url($this->canonicalUrl);
        } catch (MalformedURLException) {
            return false;
        }

        if ( ! $this->isValid($canonicalUrl)) {
            return false;
        }

        return $canonicalUrl;
    }

    private function removeQueryParametersFromResolvedUrl(): Url
    {
        $url = SpatieUrl::fromString($this->resolvedUrl->toString())->withPath($this->canonicalUrl);

        foreach (array_keys($url->getAllQueryParameters()) as $key) {
            $url = $url->withoutQueryParameter($key);
        }

        return new Url($url);
    }

    private function isRelativeUrl(): bool
    {
        return $this->canonicalUrl === $this->resolvedUrl->getPath();
    }

    private function isValid(Url $canonicalUrl): bool
    {
        if ($canonicalUrl->toString() === $this->resolvedUrl->toString()) {
            return true;
        }

        if ($canonicalUrl->getPath() === '/') {
            return false;
        }

        return $canonicalUrl->getHost() === $this->resolvedUrl->getHost() && filled($canonicalUrl->getPath());
    }
}
