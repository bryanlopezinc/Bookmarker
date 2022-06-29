<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkTitleWithWebPageTitle as UpdateBookmarkTitle;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkTitleWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateTitle(): void
    {
        $title = $this->faker->sentence;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'title' => $title,
            'description' => implode(' ', $this->faker->sentences()),
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
        ]);

        (new UpdateBookmarkTitle($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $title
        ]);
    }

    public function testWillNotUpdateTitleIfTitleWasSetByUser(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create([
            'has_custom_title' => true,
            'title' => $customTitle = $this->faker->word
        ]))->build();

        $data = BookmarkMetaData::fromArray([
            'title' => $title,
            'description' => implode(' ', $this->faker->sentences()),
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
        ]);

        (new UpdateBookmarkTitle($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $customTitle
        ]);
    }

    public function testWillNotUpdateTitle(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'title' => false,
            'description' => implode(' ', $this->faker->sentences()),
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url),
        ]);

        (new UpdateBookmarkTitle($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $model->title
        ]);
    }

    public function testWill_LimitTitleIfPageTitleIsTooLong(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'title' => "Watch key highlights of Liverpool's Premier League victory over Steven Gerrard's side at Villa Park thanks to goals from Joel Matip and Sadio Mane in either half. \n\nGet full-match replays, exclusive training access and so much more on LFCTV GO. Get 30% off an annual subscription with the code 30G022 https://www.liverpoolfc.com/watch\n\nEnjoy more content and get exclusive perks in our Liverpool FC Members Area, click here to find out more: https://www.youtube.com/LiverpoolFC/join\n\nSubscribe now to Liverpool FC on YouTube, and get notified when new videos land: https://www.youtube.com/subscription_center?add_user=LiverpoolFC\n\n#Liverpool #LFC go get even more updates visist my page or my instagram page at",
            'imageUrl' => new Url($this->faker->url),
            'description' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        (new UpdateBookmarkTitle($data))($bookmark);

        $this->assertEquals(100, strlen(Bookmark::whereKey($bookmark->id->toInt())->first()->title));
    }
}