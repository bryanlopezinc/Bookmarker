<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Jobs\UpdateBookmarkInfo;
use App\Readers\HttpClientInterface;
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
        $repository = $this->getMockBuilder(UpdateBookmarkRepositoryInterface::class)->getMock();

        $client->method('fetchBookmarkPageData')->willReturn(false);
        $repository->expects($this->never())->method('update');
        $this->swap(UpdateBookmarkRepositoryInterface::class, $repository);

        $job = (new UpdateBookmarkInfo(BookmarkBuilder::fromModel($bookmark)->build()));

        $job->handle($client);
    }
}
