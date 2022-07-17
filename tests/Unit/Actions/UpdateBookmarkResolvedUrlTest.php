<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkResolvedUrl;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkResolvedUrlTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateBookmarkResolvedUrl(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 400]))->build();

        $data = BookmarkMetaData::fromArray([
            'description' => $this->faker->sentence,
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => $url = new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($url, $bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($url, $bookmark) {
                    $this->assertEquals($url->value, $data->resolvedUrl->value);
                    $this->assertFalse($data->hasDescription);
                    $this->assertFalse($data->hasTitle);
                    $this->assertTrue($data->tags->isEmpty());
                    $this->assertFalse($data->hasCanonicalUrlHash);
                    $this->assertFalse($data->hasCanonicalUrl);
                    $this->assertTrue($data->hasResolvedUrl);
                    $this->assertFalse($data->hasPreviewImageUrl);
                    return $bookmark;
                });
        });

        (new UpdateBookmarkResolvedUrl($data))($bookmark);
    }

    private function mockRepository(\Closure $mock): void
    {
        $repository = $this->getMockBuilder(UpdateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(UpdateBookmarkRepositoryInterface::class, $repository);
    }
}
