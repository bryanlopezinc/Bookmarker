<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Models\Bookmark;
use App\Models\Source;
use App\Models\User;
use App\Readers\BookmarkMetaData;
use App\Readers\HttpClientInterface;
use App\Repositories\BookmarkRepository;
use App\Utils\UrlHasher;
use App\ValueObjects\Url;
use Closure;
use Database\Factories\BookmarkFactory;
use Database\Factories\SourceFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkWithHttpResponseTest extends TestCase
{
    use WithFaker;

    public function testWillNotExecuteWhenBookmarkHasBeenDeleted(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        $bookmark->delete();

        $this->mockClient(function (MockObject $mock) {
            $mock->expects($this->never())->method('fetchBookmarkPageData');
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillNotExecuteWhenBookmarkOwnerHasBeenDeleted(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        User::query()->whereKey($bookmark->user_id)->delete();

        $this->mockClient(function (MockObject $mock) {
            $mock->expects($this->never())->method('fetchBookmarkPageData');
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillNotExecuteWhenWebPageRequestFailed(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')->willReturn(false);
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertEquals($bookmark->getAttributes(), Bookmark::find($bookmark->id)->getAttributes());
    }

    public function test_will_update_resolved_at_attribute_after_updates(): void
    {
        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->create();

        Bookmark::updated(function (Bookmark $bookmark) {
            $this->assertNotNull($bookmark->resolved_at);
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => false,
                    'title'        => false,
                    'siteName'     => false,
                    'imageUrl'     => false,
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillUpdateBookmark(): void
    {
        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->create();

        $canonicalUrl = new Url($this->faker->url);
        $description = $this->faker->sentence;
        $imageUrl = new Url($this->faker->url);
        $title = $this->faker->sentences(1, true);
        $resolvedUrl = new Url($this->faker->url);

        $this->mockClient(function (MockObject $mock) use ($canonicalUrl, $description, $imageUrl, $title, $resolvedUrl) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => $description,
                    'title'        => $title,
                    'siteName'     => false,
                    'imageUrl'     => $imageUrl,
                    'canonicalUrl' => $canonicalUrl,
                    'resolvedUrl'  => $resolvedUrl
                ]));
        });

        $this->mockUrlHasher(function (MockObject $m) use ($canonicalUrl) {
            $m->expects($this->once())
                ->method('hashUrl')
                ->with($this->callback(function (Url $url) use ($canonicalUrl) {
                    $this->assertEquals($url->toString(), $canonicalUrl->toString());
                    return true;
                }))
                ->willReturnCallback(function (Url $url) {
                    return (new UrlHasher())->hashUrl($url);
                });
        });

        $this->handleUpdateBookmarkJob($bookmark);

        /** @var Bookmark */
        $updatedBookmark = Bookmark::query()->find($bookmark->id);

        $this->assertEquals($updatedBookmark->url_canonical, $canonicalUrl->toString());
        $this->assertEquals($updatedBookmark->description, $description);
        $this->assertEquals($updatedBookmark->preview_image_url, $imageUrl->toString());
        $this->assertEquals($updatedBookmark->title, $title);
        $this->assertEquals($updatedBookmark->resolved_url, $resolvedUrl->toString());
        $this->assertNotNull($updatedBookmark->resolved_at);
    }

    public function testWillNotUpdateDescriptionWhenDescriptionWasSetByUser(): void
    {
        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->create([
            'description_set_by_user' => true,
            'description'             => $this->faker->sentence
        ]);

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => $this->faker->sentence,
                    'imageUrl'     => false,
                    'title'        => false,
                    'siteName'     => false,
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        /** @var Bookmark */
        $updatedBookmark = Bookmark::query()->find($bookmark->id);

        $this->assertEquals($updatedBookmark->description, $bookmark->description);
    }

    public function testWill_LimitDescriptionWhenPageDescriptionIsTooLong(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        $description = str_repeat('B', 201);

        Bookmark::updated(function (Bookmark $bookmark) {
            $this->assertEquals(200, strlen($bookmark->description));
        });

        $this->mockClient(function (MockObject $mock) use ($description) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => $description,
                    'imageUrl'     => false,
                    'title'        => false,
                    'siteName'     => false,
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWill_LimitTitleWhenPageTitleIsTooLong(): void
    {
        $bookmark = BookmarkFactory::new()->create();

        Bookmark::updated(function (Bookmark $bookmark) {
            $this->assertEquals(100, strlen($bookmark->title));
        });

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => false,
                    'imageUrl'     => false,
                    'title'        => str_repeat('A', 200),
                    'siteName'     => false,
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);
    }

    public function testWillUpdateSiteName(): void
    {
        $source = SourceFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create([
            'source_id' => $source->id
        ])->setRelation('source', $source);

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => false,
                    'imageUrl'     => false,
                    'title'        => false,
                    'siteName'     => 'PlayStation',
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $source->id,
            'name' => 'PlayStation'
        ]);
    }

    public function testWillNotUpdateNameWhenSiteNameDataIsFalse(): void
    {
        $source = SourceFactory::new()->create();

        $bookmark = BookmarkFactory::new()->create([
            'source_id' =>  $source->id
        ])->setRelation('source', $source);


        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => false,
                    'imageUrl'     => false,
                    'title'        => false,
                    'siteName'     => false,
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
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
            'name'            => 'foo'
        ]);

        $bookmark = BookmarkFactory::new()->create([
            'source_id' => $source->id
        ])->setRelation('source', $source);

        $this->mockClient(function (MockObject $mock) {
            $mock->method('fetchBookmarkPageData')
                ->willReturn(BookmarkMetaData::fromArray([
                    'description'  => false,
                    'imageUrl'     => false,
                    'title'        => false,
                    'siteName'     => 'PlayStation',
                    'canonicalUrl' => false,
                    'resolvedUrl'  => new Url($this->faker->url)
                ]));
        });

        $this->handleUpdateBookmarkJob($bookmark);

        $this->assertDatabaseHas(Source::class, [
            'id'   => $source->id,
            'name' => 'PlayStation'
        ]);
    }

    private function mockClient(Closure $mock): void
    {
        $client = $this->getMockBuilder(HttpClientInterface::class)->getMock();

        $mock($client);

        $this->swap(HttpClientInterface::class, $client);
    }

    private function handleUpdateBookmarkJob(Bookmark $bookmark)
    {
        $job = (new UpdateBookmarkWithHttpResponse($bookmark));

        $job->handle(
            app(HttpClientInterface::class),
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
