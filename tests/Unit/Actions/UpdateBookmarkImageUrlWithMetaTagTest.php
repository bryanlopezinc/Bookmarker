<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkThumbnailWithWebPageImage as UpdateBookmarkImage;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Bookmark;
use App\Readers\BookmarkMetaData;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkImageUrlWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateImageUrl(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'imageUrl' => new Url('https://image.com/smike.png'),
            'description' => implode(' ', $this->faker->sentences()),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        (new UpdateBookmarkImage($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'preview_image_url' => 'https://image.com/smike.png'
        ]);
    }

    public function testWillNotUpdateImageUrl(): void
    {
        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        $data = BookmarkMetaData::fromArray([
            'imageUrl' => false,
            'description' => implode(' ', $this->faker->sentences()),
            'title' => $this->faker->sentence,
            'siteName' => $this->faker->word,
        ]);

        (new UpdateBookmarkImage($data))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'preview_image_url' => $model->preview_image_url
        ]);
    }
}
