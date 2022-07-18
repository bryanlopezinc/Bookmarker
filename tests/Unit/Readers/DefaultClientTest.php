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

class DefaultClientTest extends TestCase
{
    use WithFaker;

    public function testCannotConnectToHost(): void
    {
        $client = new DefaultClient;
        $url = $this->faker->url;

        Http::fake(fn () => throw new ConnectException('', new Request('GET', $url)));

        $response = $client->fetchBookmarkPageData(BookmarkBuilder::new()->url($url)->build());

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->reosolvedUrl->toString(), $url);
    }

    public function testResponseNotSuccessful(): void
    {
        $client = new DefaultClient;
        $url = $this->faker->url;

        Http::fake(fn () => Http::response(status: 500));

        $response = $client->fetchBookmarkPageData(BookmarkBuilder::new()->url($url)->build());

        $this->assertFalse($response->canonicalUrl);
        $this->assertFalse($response->description);
        $this->assertFalse($response->hostSiteName);
        $this->assertFalse($response->thumbnailUrl);
        $this->assertFalse($response->title);
        $this->assertEquals($response->reosolvedUrl->toString(), $url);
    }
}
