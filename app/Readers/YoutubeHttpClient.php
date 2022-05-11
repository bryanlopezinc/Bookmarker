<?php

declare(strict_types=1);

namespace App\Readers;

use App\ValueObjects\Url;
use App\DataTransferObjects\Bookmark;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

final class YoutubeHttpClient implements HttpClientInterface
{
    private const SITE_NAME = 'youtube';

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getWebPageData(Bookmark $bookmark): WebPageData|false
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'id' => $this->getVideoID($bookmark->linkToWebPage),
            'key' => $this->getGoogleApiKey(),
            'part' => 'snippet',
            'fields' => 'items(snippet/title,snippet/description,snippet/thumbnails)'
        ])->onError(function (Response $response) {
            $this->logger->error($response->toException()->getMessage());
        });

        if (!$response->ok()) {
            return false;
        }

        return WebPageData::fromArray([
            'title' => $response->json('items.0.snippet.title'),
            'description' => $response->json('items.0.snippet.description'),
            'imageUrl' => new Url($response->json('items.0.snippet.thumbnails.medium.url')),
            'siteName' => self::SITE_NAME
        ]);
    }

    private function getGoogleApiKey(): string
    {
        $key = env('GOOGLE_API_KEY');

        if (blank($key)) {
            throw new \Exception('The GOOGLE_API_KEY attribute has not been set in .env file');
        }

        return $key;
    }

    private function getVideoID(Url $url): string
    {
        $parts = parse_url($url->value);

        parse_str($parts['query'], $query);

        return $query['v'];
    }
}
