<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkTitleWithMetaTag as UpdateBookmarkTitle;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark;
use App\Readers\WebPageData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkTitleWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateTitle(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = WebPageData::fromArray([
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

        $data = WebPageData::fromArray([
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

        $data = WebPageData::fromArray([
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
}
