<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\Bookmark;
use App\ValueObjects\Url;

final class Factory implements HttpClientInterface
{
    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData
    {
        if ($this->isLinkToYoutubeVideo($bookmark->linkToWebPage)) {
            return app(YoutubeHttpClient::class)->{__FUNCTION__}($bookmark);
        }

        return (new DefaultClient)->fetchBookmarkPageData($bookmark);
    }

    private function isLinkToYoutubeVideo(Url $url): bool
    {
        return str_starts_with($url->toString(), 'https://www.youtube.com/watch');
    }
}
