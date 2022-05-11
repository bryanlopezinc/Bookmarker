<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\Bookmark;
use App\ValueObjects\Url;

final class Factory implements HttpClientInterface
{
    public function getWebPageData(Bookmark $bookmark): WebPageData
    {
        if ($this->isLinkToYoutubeVideo($bookmark->linkToWebPage)) {
            return app(YoutubeHttpClient::class)->getWebPageData($bookmark);
        }

        return (new DefaultClient)->getWebPageData($bookmark);
    }

    private function isLinkToYoutubeVideo(Url $url): bool
    {
        return str_starts_with($url->value, 'https://www.youtube.com/watch');
    }
}
