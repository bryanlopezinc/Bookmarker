<?php

declare(strict_types=1);

namespace Tests\Unit\Readers;

use Tests\TestCase;
use App\Readers\DefaultClient;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\WithFaker;
use Psr\Log\LoggerInterface;

class DefaultClientTest extends TestCase
{
    use WithFaker;

    public function testCannotResolveHost(): void
    {
        $client = new DefaultClient($this->app['log']);
        $url = $this->faker->url;

        Http::fake(fn () => throw new ConnectException(
            '',
            new Request('GET', $url),
            handlerContext: ['errno' => \CURLE_COULDNT_RESOLVE_HOST]
        ));

        $response = $client->fetchBookmarkPageData(BookmarkBuilder::new()->url($url)->build());

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->resolvedUrl->toString(), $url);
    }

    public function testOperationTimeout(): void
    {
        $client = new DefaultClient($this->app['log']);
        $url = $this->faker->url;

        Http::fake(fn () => throw new ConnectException(
            '',
            new Request('GET', $url),
            handlerContext: ['errno' => \CURLE_OPERATION_TIMEOUTED]
        ));

        $this->assertFalse(
            $client->fetchBookmarkPageData(BookmarkBuilder::new()->url($url)->build())
        );
    }

    public function testWillLogCurlErrors(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger->expects($this->once())
            ->method('critical')
            ->willReturnCallback(function (string $message, array $context) {
                $this->assertEquals($message, 'The URL you passed to libcurl used a protocol that this libcurl does not support.');
                $this->assertEquals($context['errno'], \CURLE_UNSUPPORTED_PROTOCOL);
            });

        $client = new DefaultClient($logger);
        $url = $this->faker->url;

        Http::fake(fn () => throw new ConnectException(
            'The URL you passed to libcurl used a protocol that this libcurl does not support.',
            new Request('GET', $url),
            handlerContext: ['errno' => \CURLE_UNSUPPORTED_PROTOCOL]
        ));

        $this->assertFalse(
            $client->fetchBookmarkPageData(BookmarkBuilder::new()->url($url)->build())
        );
    }

    public function testResponseNotSuccessful(): void
    {
        $client = new DefaultClient($this->app['log']);
        $url = $this->faker->url;

        Http::fake(fn () => Http::response(status: 500));

        $response = $client->fetchBookmarkPageData(BookmarkBuilder::new()->url($url)->build());

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->resolvedUrl->toString(), $url);
    }
}
