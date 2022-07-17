<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Jobs\UpdateBookmarkInfo;
use App\Readers\BookmarkMetaData;
use App\Readers\HttpClientInterface;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkInfoTest extends TestCase
{
    use WithFaker;

    public function test_will_not_perform_updates_when_web_page_request_fails(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $client = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $repository = $this->getMockBuilder(Repository::class)->getMock();

        $client->method('fetchBookmarkPageData')->willReturn(false);
        $repository->expects($this->never())->method('update');
        $this->swap(Repository::class, $repository);

        $job = (new UpdateBookmarkInfo(BookmarkBuilder::fromModel($bookmark)->build()));

        $job->handle($client, app(Repository::class));
    }

    public function test_will_update_resolved_at_attrbute_after_updates(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $client = $this->getMockBuilder(HttpClientInterface::class)->getMock();
        $repository = $this->getMockBuilder(Repository::class)->getMock();

        $client->method('fetchBookmarkPageData')
            ->willReturn(BookmarkMetaData::fromArray([
                'description' => false,
                'title' => false,
                'siteName' => false,
                'imageUrl' => false,
                'canonicalUrl' => false,
                'reosolvedUrl' => new Url($this->faker->url)
            ]));

        $repository->expects($this->exactly(2))
            ->method('update')
            ->withConsecutive([], [$this->callback(function (UpdateBookmarkData $data) {
                $this->assertTrue($data->hasResolvedAt);
                $this->assertTrue($data->resolvedAt->isSameMinute());
                return true;
            })])
            ->willReturn(BookmarkBuilder::fromModel($bookmark)->build());

        $this->swap(Repository::class, $repository);

        (new UpdateBookmarkInfo(BookmarkBuilder::fromModel($bookmark)->build()))->handle($client, $repository);
    }
}
