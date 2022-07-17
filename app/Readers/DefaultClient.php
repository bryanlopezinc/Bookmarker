<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\Bookmark;
use App\ValueObjects\Url;
use Illuminate\Support\Facades\Http;

final class DefaultClient implements HttpClientInterface
{
    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData|false
    {
        $response = Http::accept('text/html')
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36')
            ->get($bookmark->linkToWebPage->value);

        if (!$response->ok()) {
            return false;
        }

        $DOMReader = new DOMReader($response->body(), $resolvedUrl = new Url($response->effectiveUri()));

        return BookmarkMetaData::fromArray([
            'title' => $DOMReader->getPageTitle(),
            'description' => $DOMReader->getPageDescription(),
            'imageUrl' => $DOMReader->getPreviewImageUrl(),
            'siteName' => $DOMReader->getSiteName(),
            'canonicalUrl' => $DOMReader->getCanonicalUrl(),
            'reosolvedUrl' => $resolvedUrl
        ]);
    }
}
