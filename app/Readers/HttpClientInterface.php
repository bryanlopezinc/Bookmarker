<?php

declare(strict_types=1);

namespace App\Readers;

use App\Models\Bookmark;

interface HttpClientInterface
{
    /**
     * Fetch the Bookmark's page data as an object.
     * This method will return false if the http request fails.
     */
    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData|false;
}
