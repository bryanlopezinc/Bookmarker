<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Bookmark;
use App\Readers\BookmarkMetaData;
use App\Readers\HttpClientInterface;
use App\ValueObjects\Url;
use RuntimeException;

final class TestHttpClient implements HttpClientInterface
{
    public function __construct()
    {
        if ( ! app()->environment('testing')) {
            throw new RuntimeException(__CLASS__ . ' can only be used in test environments');
        }
    }

    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData|false
    {
        $faker = fake();
        $url = new Url($bookmark->url);

        return BookmarkMetaData::fromArray([
            'description'  => $faker->sentence,
            'title'        => $faker->sentence,
            'hostSiteName' => $url->getHost(),
            'iconUrl'      => new Url($faker->imageUrl),
            'canonicalUrl' => $url,
            'resolvedUrl'  => $url
        ]);
    }
}
