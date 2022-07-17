<?php

declare(strict_types=1);

namespace App\Readers;

use App\Exceptions\MalformedURLException;
use App\ValueObjects\Url;
use Spatie\Url\Url as SpatieUrl;

final class ResolveCanonicalUrlValue
{
    public function __construct(private string $value, private Url $resolvedUrl)
    {
    }

    public function __invoke(): Url|false
    {
        if ($this->canonicalUrlIsResolvedUrlPath()) {
            return $this->removeQueryParametersFromResolvedUrl();
        }

        try {
            $url = new Url($this->value);
        } catch (MalformedURLException) {
            return false;
        }

        if (!$this->isValid($url)) {
            return false;
        }

        return $url;
    }

    private function removeQueryParametersFromResolvedUrl(): Url
    {
        $url = SpatieUrl::fromString($this->resolvedUrl->value)->withPath($this->value);

        foreach (array_keys($url->getAllQueryParameters()) as $key) {
            $url = $url->withoutQueryParameter($key);
        }

        return new Url($url);
    }

    private function canonicalUrlIsResolvedUrlPath(): bool
    {
        return $this->value === $this->resolvedUrl->getPath();
    }

    private function isValid(Url $url): bool
    {
        if ($url->getPath() === '/') {
            return false;
        }

        return $url->getHostName() === $this->resolvedUrl->getHostName() && filled($url->getPath());
    }
}
