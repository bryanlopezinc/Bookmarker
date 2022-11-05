<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Contracts\UpdateBookmarkRepositoryInterface as Repository;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use App\Models\Source;
use App\Readers\BookmarkMetaData;
use App\Readers\HttpClientInterface;
use App\Repositories\BookmarkRepository;
use App\Repositories\UserRepository;
use App\Utils\UrlHasher;
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

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) {
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

    public function test_will_not_perform_any_update_if_user_has_deleted_account(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')
                ->once()
                ->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
        });

        $this->mock(UserRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('findByID')->once()->andReturn(false);
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

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
            $mock->shouldReceive('findManyById')
                ->once()
                ->andReturn(collect([BookmarkBuilder::fromModel($bookmark)->build()]));
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

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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

    public function test_will_update_resolved_at_attribute_after_updates(): void
    {
        $bookmark = BookmarkFactory::new()->make(['id' => 5001]);

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
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

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => $resolvedUrl
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


        $this->mockUrlHasher(function (MockObject $m) use ($canonicalUrl) {
            $m->expects($this->once())
                ->method('hashUrl')
                ->with($this->callback(function (Url $url) use ($canonicalUrl) {
                    $this->assertEquals($url->toString(), $canonicalUrl->toString());
                    return true;
                }))
                ->willReturnCallback(function (Url $url) {
                    return (new UrlHasher)->hashUrl($url);
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillNotUpdateDescriptionIfDescriptionWasSetByUser(): void
    {
        $bookmark = BookmarkFactory::new()->make([
            'id' => 5001,
            'description_set_by_user' => true
        ]);

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $m) use ($bookmark) {
            $m->expects($this->once())
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

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $m) use ($bookmark) {
            $m->expects($this->once())
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

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->mockRepository(function (MockObject $m) use ($bookmark) {
            $m->expects($this->once())
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
        $source = SourceFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create([
            'source_id' => $source->id
        ])->setRelation('source', $source);

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $source->id,
            'name' => 'PlayStation'
        ]);
    }

    public function testWillNotUpdateNameIfSiteNameDataIsFalse(): void
    {
        $source = SourceFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create([
            'source_id' =>  $source->id
        ])->setRelation('source', $source);

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $source->id,
            'name' => $source->name
        ]);
    }

    public function testWillNotUpdateNameIfNameHasBeenUpdated(): void
    {
        $source = SourceFactory::new()->create([
            'name_updated_at' => now(),
            'name' => 'foosite'
        ]);

        $bookmark = BookmarkFactory::new()->create([
            'source_id' => $source->id
        ])->setRelation('source', $source);

        $this->mock(BookmarkRepository::class, function (MockInterface $mock) use ($bookmark) {
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
                    'resolvedUrl' => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $source->id,
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
            app(UrlHasher::class),
            app(BookmarkRepository::class)
        );
    }

    private function mockUrlHasher(\Closure $mock): void
    {
        $hasher = $this->getMockBuilder(UrlHasher::class)->getMock();

        $mock($hasher);

        $this->swap(UrlHasher::class, $hasher);
    }
}
