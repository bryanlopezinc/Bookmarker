<?php

declare(strict_types=1);

namespace App\Readers;

use App\Models\Bookmark;
use App\ValueObjects\Url;

final class Factory implements HttpClientInterface
{
    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData|false
    {
        if ($this->isLinkToYoutubeVideo($bookmark->url)) {
            return (new YoutubeHttpClient(app('log')))->fetchBookmarkPageData($bookmark);
        }

        return (new DefaultClient(app('log')))->fetchBookmarkPageData($bookmark);
    }

    private function isLinkToYoutubeVideo(string $url): bool
    {
        return str_starts_with($url, 'https://www.youtube.com/watch');
    }
}
