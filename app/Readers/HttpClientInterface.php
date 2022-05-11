<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\Bookmark;

interface HttpClientInterface
{
    /**
     * Get the web page data or return false if page request fails
     */
    public function getWebPageData(Bookmark $bookmark): WebPageData|false;
}
