<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\Contracts\UrlHasherInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use App\Models\Source;
use App\Readers\BookmarkMetaData;
use App\Readers\HttpClientInterface;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\Url;
use Closure;
use Database\Factories\BookmarkFactory;
use Database\Factories\SourceFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkWithHttpResponseTest extends TestCase
{
    use WithFaker;

    public function test_will_not_perform_any_update_if_bookmark_has_been_deleted(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect());
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->expects($this->never())->method('fetchBookmarkPageData');
        });

        $this->mockRepository(function (MockObject $mock) {
            $mock->expects($this->never())->method('update');
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function test_will_not_perform_any_update_if_web_page_request_fails(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')->willReturn(false);
        });

        $this->mockRepository(function (MockObject $mock) {
            $mock->expects($this->never())->method('update');
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function test_will_not_make_http_requests_if_bookmark_url_is_not_http_protocol(): void
    {
        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->make([
            'id' => 3982,
            'url' => 'payto://iban/DE75512108001245126199?amount=EUR:200.0&message=hello'
        ]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->expects($this->never())->method('fetchBookmarkPageData');
        });

        $this->mockRepository(function (MockObject $mock) use ($bookmark) {
            $mock->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertEquals($bookmark->url, $data->bookmark->resolvedUrl->toString());
                    $this->assertTrue($data->hasResolvedAt());
                    $this->assertTrue($data->bookmark->resolvedAt->isSameMinute());
                    $this->assertFalse($data->hasCanonicalUrl());
                    $this->assertFalse($data->hasCanonicalUrlHash());
                    $this->assertFalse($data->hasDescription());
                    $this->assertFalse($data->hasThumbnailUrl());
                    $this->assertFalse($data->hasTitle());
                    $this->assertTrue($data->hasResolvedUrl());

                    return BookmarkBuilder::fromModel($bookmark)->build();
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function test_will_update_resolved_at_attrbute_after_updates(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => false,
                    'title' => false,
                    'siteName' => false,
                    'imageUrl' => false,
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $mock) use ($bookmark) {
            $mock->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertTrue($data->hasResolvedAt());
                    $this->assertTrue($data->bookmark->resolvedAt->isSameMinute());
                    $this->assertFalse($data->hasCanonicalUrl());
                    $this->assertFalse($data->hasCanonicalUrlHash());
                    $this->assertFalse($data->hasDescription());
                    $this->assertFalse($data->hasThumbnailUrl());
                    $this->assertFalse($data->hasTitle());
                    $this->assertTrue($data->hasResolvedUrl());

                    return BookmarkBuilder::fromModel($bookmark)->build();
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillUpdateBookmark(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $canonicalUrl = new Url($this->faker->url);
        $description = $this->faker->sentences(1, true);
        $imageUrl = new Url($this->faker->url);
        $title = $this->faker->sentences(1, true);
        $resolvedUrl = new Url($this->faker->url);

        $this->mockClient(function (MockObject $mock) use ($canonicalUrl, $description, $imageUrl, $title, $resolvedUrl) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => $description,
                    'title' => $title,
                    'siteName' => false,
                    'imageUrl' => $imageUrl,
                    'canonicalUrl' => $canonicalUrl,
                    'reosolvedUrl' => $resolvedUrl
                ]));
        });

        $this->mockRepository(function (MockObject $mock) use ($bookmark, $canonicalUrl, $description, $imageUrl, $title, $resolvedUrl) {
            $mock->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark, $canonicalUrl, $description, $imageUrl, $title, $resolvedUrl) {
                    $this->assertEquals($canonicalUrl->toString(), $data->bookmark->canonicalUrl->toString());
                    $this->assertEquals($description, $data->bookmark->description->value);
                    $this->assertEquals($imageUrl->toString(), $data->bookmark->thumbnailUrl->toString());
                    $this->assertEquals($title, $data->bookmark->title->value);
                    $this->assertEquals($resolvedUrl->toString(), $data->bookmark->resolvedUrl->toString());
                    $this->assertTrue($data->hasResolvedAt());
                    $this->assertTrue($data->bookmark->resolvedAt->isSameMinute());
                    $this->assertTrue($data->hasCanonicalUrl());
                    $this->assertTrue($data->hasCanonicalUrlHash());
                    $this->assertTrue($data->hasDescription());
                    $this->assertTrue($data->hasThumbnailUrl());
                    $this->assertTrue($data->hasTitle());
                    $this->assertTrue($data->hasResolvedUrl());

                    return BookmarkBuilder::fromModel($bookmark)->build();
                });
        });


        $this->mockUrlHasher(function (MockObject $repository) use ($canonicalUrl) {
            $repository->expects($this->once())
                ->method('hashUrl')
                ->with($this->callback(function (Url $url) use ($canonicalUrl) {
                    $this->assertEquals($url->toString(), $canonicalUrl->toString());
                    return true;
                }));
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillNotUpdateDescriptionIfDescriptionWasSetByuser(): void
    {
        $bookmark = BookmarkFactory::new()->make([
            'id' => 5001,
            'description_set_by_user' => true
        ]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => $this->faker->sentence,
                    'imageUrl' => false,
                    'title' => false,
                    'siteName' => false,
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertFalse($data->hasDescription());

                    return BookmarkBuilder::fromModel($bookmark)->build();
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWill_LimitDescriptionIfPageDescriptionIsTooLong(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);
        $description = str_repeat('B', 201);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) use ($description) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => $description,
                    'imageUrl' => false,
                    'title' => false,
                    'siteName' => false,
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertTrue($data->hasDescription());
                    $this->assertEquals(200, strlen($data->bookmark->description->value));

                    return BookmarkBuilder::fromModel($bookmark)->build();
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWill_LimitTitleIfPageTitleIsTooLong(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => false,
                    'imageUrl' => false,
                    'title' => str_repeat('A', 200),
                    'siteName' => false,
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertTrue($data->hasTitle());
                    $this->assertEquals(100, strlen($data->bookmark->title->value));

                    return BookmarkBuilder::fromModel($bookmark)->build();
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillUpdateSiteName(): void
    {
        $site = SourceFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create([
            'source_id' => $site->id
        ])->setRelation('site', $site);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => false,
                    'imageUrl' => false,
                    'title' => false,
                    'siteName' => 'PlayStation',
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $site->id,
            'name' => 'PlayStation'
        ]);
    }

    public function testWillNotUpdateNameIfSiteNameDataIsFalse(): void
    {
        $site = SourceFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create([
            'source_id' =>  $site->id
        ])->setRelation('site', $site);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => false,
                    'imageUrl' => false,
                    'title' => false,
                    'siteName' => false,
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $site->id,
            'name' => $site->name
        ]);
    }

    public function testWillNotUpdateNameIfNameHasBeenUpdated(): void
    {
        $site = SourceFactory::new()->create([
            'name_updated_at' => now(),
            'name' => 'foosite'
        ]);

        $bookmark = BookmarkFactory::new()->create([
            'source_id' => $site->id
        ])->setRelation('site', $site);

        $this->mock(FetchBookmarksRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')->once()->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description' => false,
                    'imageUrl' => false,
                    'title' => false,
                    'siteName' => 'PlayStation',
                    'canonicalUrl' => false,
                    'reosolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $site->id,
            'name' => 'foosite'
        ]);
    }

    private function mockClient(Closure $mock): void
    {
        $client = $this->getMockBuilder(HttpClientInterface::class)->getMock();

        $mock($client);

        $this->swap(HttpClientInterface::class, $client);
    }

    private function mockRepository(Closure $mock): void
    {
        $repository = $this->getMockBuilder(Repository::class)->getMock();

        $mock($repository);

        $this->swap(Repository::class, $repository);
    }

    private function handleUpdateBookmarkJob(Bookmark $bookmark)
    {
        $job = (new UpdateBookmarkWithHttpResponse(BookmarkBuilder::fromModel($bookmark)->build()));

        $job->handle(
            app(HttpClientInterface::class),
            app(Repository::class),
            app(UrlHasherInterface::class),
            app(FetchBookmarksRepository::class)
        );
    }

    private function mockUrlHasher(\Closure $mock): void
    {
        $hasher = $this->getMockBuilder(UrlHasherInterface::class)->getMock();

        $mock($hasher);

        $this->swap(UrlHasherInterface::class, $hasher);
    }
}
