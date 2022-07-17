<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkDescriptionWithWebPageDescription as UpdateBookmarkDescription;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkDescriptionWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateDescription(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 400]))->build();

        $data = BookmarkMetaData::fromArray([
            'description' => $description = $this->faker->sentence,
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($description, $bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($description, $bookmark) {
                    $this->assertEquals($description, $data->description->value);
                    $this->assertTrue($data->hasDescription);
                    $this->assertFalse($data->hasTitle);
                    $this->assertTrue($data->tags->isEmpty());
                    $this->assertFalse($data->hasCanonicalUrlHash);
                    $this->assertFalse($data->hasCanonicalUrl);
                    $this->assertFalse($data->hasResolvedUrl);
                    $this->assertFalse($data->hasPreviewImageUrl);
                    return $bookmark;
                });
        });

        (new UpdateBookmarkDescription($data))($bookmark);
    }

    private function mockRepository(\Closure $mock): void
    {
        $repository = $this->getMockBuilder(UpdateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(UpdateBookmarkRepositoryInterface::class, $repository);
    }

    public function testWillNotUpdateDescriptionIfDescriptionWasSetByuser(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make([
            'description_set_by_user' => true
        ]))->build();

        $data = BookmarkMetaData::fromArray([
            'description' => implode(' ', $this->faker->sentences()),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->never())->method('update')->willReturn($bookmark);
        });

        (new UpdateBookmarkDescription($data))($bookmark);
    }

    public function testWillNotUpdateDescriptionIfNoDescriptionTagIsPresent(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make())->build();

        $data = BookmarkMetaData::fromArray([
            'description' => false,
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->never())->method('update')->willReturn($bookmark);
        });

        (new UpdateBookmarkDescription($data))($bookmark);
    }

    public function testWill_LimitDescriptionIfPageDescriptionIsTooLong(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 500]))->build();

        $data = BookmarkMetaData::fromArray([
            'description' => "Watch key highlights of Liverpool's Premier League victory over Steven Gerrard's side at Villa Park thanks to goals from Joel Matip and Sadio Mane in either half. \n\nGet full-match replays, exclusive training access and so much more on LFCTV GO. Get 30% off an annual subscription with the code 30G022 https://www.liverpoolfc.com/watch\n\nEnjoy more content and get exclusive perks in our Liverpool FC Members Area, click here to find out more: https://www.youtube.com/LiverpoolFC/join\n\nSubscribe now to Liverpool FC on YouTube, and get notified when new videos land: https://www.youtube.com/subscription_center?add_user=LiverpoolFC\n\n#Liverpool #LFC go get even more updates visist my page or my instagram page at",
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'canonicalUrl' => $url = new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertEquals(200, strlen($data->description->value));
                    return $bookmark;
                });
        });

        (new UpdateBookmarkDescription($data))($bookmark);
    }
}
