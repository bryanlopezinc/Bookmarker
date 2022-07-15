<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkThumbnailWithWebPageImage as UpdateBookmarkImage;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkImageUrlWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateImageUrl(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 22]))->build();

        $data = BookmarkMetaData::fromArray([
            'imageUrl' => $url = new Url($this->faker->url),
            'description' => implode(' ', $this->faker->sentences()),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        $this->mockRepository(function (MockObject $repository) use ($url, $bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($url, $bookmark) {
                    $this->assertEquals($url->value, $data->previewImageUrl->value);
                    $this->assertTrue($data->ownerId->equals($bookmark->ownerId));
                    $this->assertFalse($data->hasDescription);
                    $this->assertFalse($data->hasTitle);
                    $this->assertTrue($data->tags->isEmpty());
                    return $bookmark;
                });
        });

        (new UpdateBookmarkImage($data))($bookmark);
    }

    private function mockRepository(\Closure $mock): void
    {
        $repository = $this->getMockBuilder(UpdateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(UpdateBookmarkRepositoryInterface::class, $repository);
    }

    public function testWillNotUpdateImageUrlWhenImageUrl_IsInvalid(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make())->build();

        $data = BookmarkMetaData::fromArray([
            'imageUrl' => false,
            'description' => implode(' ', $this->faker->sentences()),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->never())->method('update')->willReturn($bookmark);
        });

        (new UpdateBookmarkImage($data))($bookmark);
    }
}
