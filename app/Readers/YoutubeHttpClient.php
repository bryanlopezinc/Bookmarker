<?php

declare(strict_types=1);

namespace App\Readers;

use App\Models\Bookmark;
use App\ValueObjects\Url;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

final class YoutubeHttpClient implements HttpClientInterface
{
    private const SITE_NAME = 'youtube';

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function fetchBookmarkPageData(Bookmark $bookmark): BookmarkMetaData|false
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'id'     => $this->getVideoID($bookmark->url),
            'key'    => $this->getGoogleApiKey(),
            'part'   => 'snippet',
            'fields' => 'items(snippet/title,snippet/description,snippet/thumbnails)'
        ])->onError(function (Response $response) {
            $message = $response->toException()?->getMessage();

            if ($message !== null) {
                $this->logger->error($message);
            }
        });

        if (!$response->ok()) {
            return false;
        }

        return BookmarkMetaData::fromArray([
            'title'        => $response->json('items.0.snippet.title'),
            'description'  => $response->json('items.0.snippet.description'),
            'thumbnailUrl' => new Url($response->json('items.0.snippet.thumbnails.medium.url')),
            'hostSiteName' => self::SITE_NAME,
            'canonicalUrl' => new Url($bookmark->url_canonical),
            'resolvedUrl'  => new Url($bookmark->resolved_url)
        ]);
    }

    private function getGoogleApiKey(): string
    {
        $apiKey = config($key = 'services.youtube.key');

        if (blank($apiKey)) {
            throw new \Exception('The ' . $key . ' is missing or has not been set');
        }

        return $apiKey;
    }

    private function getVideoID(string $url): string
    {
        /** @var string[] */
        $parts = parse_url($url);

        parse_str($parts['query'], $query);

        return $query['v']; //@phpstan-ignore-line
    }
}
