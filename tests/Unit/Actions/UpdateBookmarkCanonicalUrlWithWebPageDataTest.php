<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkCanonicalUrlWithWebPageData as UpdateBookmark;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\Contracts\UrlHasherInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkCanonicalUrlWithWebPageDataTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateUrl(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 400]))->build();

        $data = BookmarkMetaData::fromArray([
            'description' => $this->faker->sentence,
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'canonicalUrl' => $url = new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockUrlHasher(function (MockObject $repository) use ($url) {
            $repository->expects($this->once())
                ->method('hashCanonicalUrl')
                ->with($this->callback(function (Url $canonicalUrl) use ($url) {
                    $this->assertEquals($canonicalUrl->value, $url->value);
                    return true;
                }));
        });

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertTrue($data->hasCanonicalUrlHash);
                    $this->assertTrue($data->hasCanonicalUrl);
                    $this->assertTrue($data->tags->isEmpty());
                    $this->assertFalse($data->hasResolvedUrl);
                    $this->assertFalse($data->hasDescription);
                    $this->assertFalse($data->hasTitle);
                    $this->assertFalse($data->hasPreviewImageUrl);
                    $this->assertFalse($data->hasResolvedUrl);
                    return $bookmark;
                });
        });

        (new UpdateBookmark($data))($bookmark);
    }

    private function mockRepository(\Closure $mock): void
    {
        $repository = $this->getMockBuilder(UpdateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(UpdateBookmarkRepositoryInterface::class, $repository);
    }

    private function mockUrlHasher(\Closure $mock): void
    {
        $hasher = $this->getMockBuilder(UrlHasherInterface::class)->getMock();

        $mock($hasher);

        $this->swap(UrlHasherInterface::class, $hasher);
    }

    public function testWillNotUpdateUrl_If_dataUrl_Isfalse(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make())->build();

        $data = BookmarkMetaData::fromArray([
            'description' => false,
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
            'canonicalUrl' => false,
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->never())->method('update');
        });

        $this->mockUrlHasher(function (MockObject $repository) {
            $repository->expects($this->never())->method('hashCanonicalUrl');
        });

        (new UpdateBookmark($data))($bookmark);
    }
}
