<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkDescriptionWithWebPageDescription as UpdateBookmarkDescription;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkDescriptionWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateDescription(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'description' => $description = $this->faker->sentence,
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        (new UpdateBookmarkDescription($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $description
        ]);
    }

    public function testWillNotUpdateDescriptionIfDescriptionWasSetByuser(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create([
            'description_set_by_user' => true
        ]))->build();

        $data = BookmarkMetaData::fromArray([
            'description' => implode(' ', $this->faker->sentences()),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url)
        ]);

        (new UpdateBookmarkDescription($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $bookmark->description->value
        ]);
    }

    public function testWillNotUpdateDescriptionIfNoTagIsPresent(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'description' => false,
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
            'imageUrl' => new Url($this->faker->url)
        ]);

        (new UpdateBookmarkDescription($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $model->description
        ]);
    }

    public function testWill_LimitDescriptionIfPageDescriptionIsTooLong(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'description' => "Watch key highlights of Liverpool's Premier League victory over Steven Gerrard's side at Villa Park thanks to goals from Joel Matip and Sadio Mane in either half. \n\nGet full-match replays, exclusive training access and so much more on LFCTV GO. Get 30% off an annual subscription with the code 30G022 https://www.liverpoolfc.com/watch\n\nEnjoy more content and get exclusive perks in our Liverpool FC Members Area, click here to find out more: https://www.youtube.com/LiverpoolFC/join\n\nSubscribe now to Liverpool FC on YouTube, and get notified when new videos land: https://www.youtube.com/subscription_center?add_user=LiverpoolFC\n\n#Liverpool #LFC go get even more updates visist my page or my instagram page at",
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        (new UpdateBookmarkDescription($data))($bookmark);

        $this->assertEquals(200, strlen(Bookmark::whereKey($bookmark->id->toInt())->first()->description));
    }
}