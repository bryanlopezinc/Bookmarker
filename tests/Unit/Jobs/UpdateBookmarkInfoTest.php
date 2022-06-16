<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Jobs\UpdateBookmarkInfo;
use App\Models\Bookmark;
use App\Readers\HttpClientInterface;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkInfoTest extends TestCase
{
    use WithFaker;

    public function test_will_not_perform_updates_when_web_page_request_fails(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        $client = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $client->method('getWebPageData')->willReturn(false);

        $job = (new UpdateBookmarkInfo(BookmarkBuilder::fromModel($bookmark)->build()));

        $job->handle($client);

        /** @var Bookmark */
        $resullt = Bookmark::query()->where('id', $bookmark->id)->first();

        $actual = collect([
            'preview_image_url' => $resullt->preview_image_url,
            'description' => $resullt->description,
            'title' => $resullt->title,
            'has_custom_title' => false,
            'description_set_by_user' => false
        ])->sortKeys();

        $expected = collect($bookmark->toArray())->only($actual->keys())->sortKeys();

        $this->assertEquals($expected, $actual);
    }
}