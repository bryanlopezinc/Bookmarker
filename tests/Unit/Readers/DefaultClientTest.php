<?php

declare(strict_types=1);

namespace Tests\Unit\Readers;

use App\Models\Bookmark;
use Tests\TestCase;
use App\Readers\DefaultClient;
use App\Readers\DOMReader;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\WithFaker;
use Psr\Log\LoggerInterface;

class DefaultClientTest extends TestCase
{
    use WithFaker;

    public function testWhenHostCannotBeResolved(): void
    {
        $bookmark = new Bookmark();
        $bookmark->url = $url = $this->faker->url;

        $client = new DefaultClient($this->app['log']);

        Http::fake(fn () => throw new ConnectException(
            '',
            new Request('GET', $url),
            handlerContext: ['errno' => \CURLE_COULDNT_RESOLVE_HOST]
        ));

        $response = $client->fetchBookmarkPageData($bookmark);

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->resolvedUrl->toString(), $url);
    }

    public function testWhenOperationTimedOuted(): void
    {
        $bookmark = new Bookmark();
        $bookmark->url = $url = $this->faker->url;

        $client = new DefaultClient($this->app['log']);

        Http::fake(fn () => throw new ConnectException(
            '',
            new Request('GET', $url),
            handlerContext: ['errno' => \CURLE_OPERATION_TIMEOUTED]
        ));

        $this->assertFalse(
            $client->fetchBookmarkPageData($bookmark)
        );
    }

    public function testWillLogCurlErrors(): void
    {
        $bookmark = new Bookmark();
        $bookmark->url = $url = $this->faker->url;

        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger->expects($this->once())
            ->method('critical')
            ->willReturnCallback(function (string $message, array $context) {
                $this->assertEquals($message, 'The URL you passed to libcurl used a protocol that this libcurl does not support.');
                $this->assertEquals($context['errno'], \CURLE_UNSUPPORTED_PROTOCOL);
            });

        $client = new DefaultClient($logger);

        Http::fake(fn () => throw new ConnectException(
            'The URL you passed to libcurl used a protocol that this libcurl does not support.',
            new Request('GET', $url),
            handlerContext: ['errno' => \CURLE_UNSUPPORTED_PROTOCOL]
        ));

        $this->assertFalse(
            $client->fetchBookmarkPageData($bookmark)
        );
    }

    public function testWhenResponseWasNotSuccessful(): void
    {
        $bookmark = new Bookmark();
        $bookmark->url = $url = $this->faker->url;

        $client = new DefaultClient($this->app['log']);

        Http::fake(fn () => Http::response(status: 500));

        $response = $client->fetchBookmarkPageData($bookmark);

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->resolvedUrl->toString(), $url);
    }

    public function testWillReturnEmptyResponseWhenContentTypeIsNotHtml(): void
    {
        $bookmark = new Bookmark();
        $bookmark->url = $url = $this->faker->url;

        $reader = $this->getMockBuilder(DOMReader::class)->getMock();

        $reader->expects($this->never())->method($this->anything());

        $client = new DefaultClient($this->app['log'], $reader);

        $body = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta name="application-name" content="Xbox">
                <meta property="og:description" content="foo">
                <meta property="og:image" content="https://image.com/smike.png">
                <meta property="og:title" content="title">
                <meta property="og:url" content="https://www.rottentomatoes.com/m/thor_love_and_thunder">
            </head>
            </html>
        HTML;

        Http::fake(fn () => Http::response($body, headers: ['content-type' => 'application/json']));

        $response = $client->fetchBookmarkPageData($bookmark);

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->resolvedUrl->toString(), $url);
    }
}
