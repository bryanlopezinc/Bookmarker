<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkDescriptionWithWebPageDescription as UpdateBookmarkDescription;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark;
use App\Readers\WebPageData;
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

        $data = WebPageData::fromArray([
            'description' => $description = implode(' ', $this->faker->sentences()),
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

        $data = WebPageData::fromArray([
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

        $data = WebPageData::fromArray([
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
}
