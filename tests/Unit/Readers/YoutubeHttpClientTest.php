<?php

declare(strict_types=1);

namespace Tests\Unit\Readers;

use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Readers\YoutubeHttpClient;
use Database\Factories\BookmarkFactory;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class YoutubeHttpClientTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $expectedJson = file_get_contents(base_path('tests/stubs/Youtube/videorequest.json'));

        Http::fake(fn () => Http::response($expectedJson));

        $bookmark = BookmarkFactory::new()->make([
            'url' => 'https://www.youtube.com/watch?v=MBO0AiAD0DQ'
        ]);

        $client = new YoutubeHttpClient(app('log'));

        $response = $client->fetchBookmarkPageData(BookmarkBuilder::fromModel($bookmark)->build());

        $data = json_decode($expectedJson);

        $this->assertEquals(data_get($data, 'items.0.snippet.thumbnails.medium.url'), $response->thumbnailUrl->toString());
        $this->assertEquals(data_get($data, 'items.0.snippet.title'),  $response->title);
        $this->assertEquals(data_get($data, 'items.0.snippet.description'),  $response->description);
        $this->assertEquals('youtube',  $response->hostSiteName);
    }

    public function testWillThrowExceptionIf_Api_KeyIsNotSet(): void
    {
        $this->expectExceptionMessage('The services.youtube.key is missing or has not been set');

        config(['services.youtube.key' => null]);

        $bookmark = BookmarkFactory::new()->make([
            'url' => 'https://www.youtube.com/watch?v=MBO0AiAD0DQ'
        ]);

        $client = new YoutubeHttpClient(app('log'));

        $client->fetchBookmarkPageData(BookmarkBuilder::fromModel($bookmark)->build());
    }

    public function testWillLogErrorResponse(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $logger->expects($this->once())->method('error');

        Http::fake(fn () => Http::response(status: 403));

        $bookmark = BookmarkFactory::new()->make([
            'url' => 'https://www.youtube.com/watch?v=MBO0AiAD0DQ'
        ]);

        $client = new YoutubeHttpClient($logger);

        $this->assertFalse($client->fetchBookmarkPageData(BookmarkBuilder::fromModel($bookmark)->build()));
    }
}
