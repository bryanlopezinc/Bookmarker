<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\Bookmark;
use Illuminate\Support\Facades\Http;

final class DefaultClient implements HttpClientInterface
{
    public function getWebPageData(Bookmark $bookmark): WebPageData|false
    {
        $response = Http::accept('text/html')
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36')
            ->get($bookmark->linkToWebPage->value);

        if (!($response->ok() || $response->redirect())) {
            return false;
        }

        $reader = new Reader($response->body());

        return WebPageData::fromArray([
            'title' => $reader->getPageTitle(),
            'description' => $reader->getPageDescription(),
            'imageUrl' => $reader->getPreviewImageUrl(),
            'siteName' => $reader->getSiteName()
        ]);
    }
}
