<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkTitleWithWebPageTitle as UpdateBookmarkTitle;
use App\Contracts\UpdateBookmarkRepositoryInterface;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class UpdateBookmarkTitleWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateTitle(): void
    {
        $title = $this->faker->sentence;

        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 30]))->build();

        $data = BookmarkMetaData::fromArray([
            'title' => $title,
            'description' => implode(' ', $this->faker->sentences()),
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($title, $bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($title, $bookmark) {
                    $this->assertFalse($data->hasPreviewImageUrl);
                    $this->assertFalse($data->hasDescription);
                    $this->assertEquals($data->title->value, $title);
                    $this->assertEquals($data->id->toInt(), $bookmark->id->toInt());
                    $this->assertTrue($data->tags->isEmpty());
                    $this->assertFalse($data->hasCanonicalUrlHash);
                    $this->assertFalse($data->hasCanonicalUrl);
                    $this->assertFalse($data->hasResolvedUrl);
                    return $bookmark;
                });
        });

        (new UpdateBookmarkTitle($data))($bookmark);
    }

    private function mockRepository(\Closure $mock): void
    {
        $repository = $this->getMockBuilder(UpdateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(UpdateBookmarkRepositoryInterface::class, $repository);
    }

    public function testWillNotUpdateTitleIfTitleWasSetByUser(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make([
            'has_custom_title' => true,
        ]))->build();

        $data = BookmarkMetaData::fromArray([
            'title' => $title,
            'description' => implode(' ', $this->faker->sentences()),
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->never())->method('update')->willReturn($bookmark);
        });

        (new UpdateBookmarkTitle($data))($bookmark);
    }

    public function testWillNotUpdateTitleWhenMetaDataHasNoTitle(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make())->build();

        $data = BookmarkMetaData::fromArray([
            'title' => false,
            'description' => implode(' ', $this->faker->sentences()),
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->never())->method('update')->willReturn($bookmark);
        });

        (new UpdateBookmarkTitle($data))($bookmark);
    }

    public function testWill_LimitTitleIfPageTitleIsTooLong(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->make(['id' => 4040]))->build();

        $data = BookmarkMetaData::fromArray([
            'title' => "Watch key highlights of Liverpool's Premier League victory over Steven Gerrard's side at Villa Park thanks to goals from Joel Matip and Sadio Mane in either half. \n\nGet full-match replays, exclusive training access and so much more on LFCTV GO. Get 30% off an annual subscription with the code 30G022 https://www.liverpoolfc.com/watch\n\nEnjoy more content and get exclusive perks in our Liverpool FC Members Area, click here to find out more: https://www.youtube.com/LiverpoolFC/join\n\nSubscribe now to Liverpool FC on YouTube, and get notified when new videos land: https://www.youtube.com/subscription_center?add_user=LiverpoolFC\n\n#Liverpool #LFC go get even more updates visist my page or my instagram page at",
            'imageUrl' => new Url($this->faker->url),
            'description' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'canonicalUrl' => new Url($this->faker->url),
            'reosolvedUrl' => new Url($this->faker->url)
        ]);

        $this->mockRepository(function (MockObject $repository) use ($bookmark) {
            $repository->expects($this->once())
                ->method('update')
                ->willReturnCallback(function (UpdateBookmarkData $data) use ($bookmark) {
                    $this->assertEquals(100, strlen($data->title->value));
                    return $bookmark;
                });
        });

        (new UpdateBookmarkTitle($data))($bookmark);
    }
}
