<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkDescriptionWithMetaTag as UpdateBookmarkDescription;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DOMReader;
use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkDescriptionWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateDescriptionIfOpenGraphTagIsPresent(): void
    {
        $description = implode(' ', $this->faker->sentences());

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="description" content="A foo bar site">
                <meta property="og:description" content="$description">
                <title>Document</title>
            </head>
            <body>

            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkDescription(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $description
        ]);
    }

    public function testWillNotUpdateDescriptionIfDescriptionWasSetByuser(): void
    {
        $description = implode(' ', $this->faker->sentences());

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="description" content="A foo bar site">
                <meta property="og:description" content="$description">
                <title>Document</title>
            </head>
            <body>

            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create([
            'description_set_by_user' => true
        ]))->build();

        (new UpdateBookmarkDescription(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $bookmark->description->value
        ]);
    }

    public function testWillUpdateDescriptionWithMetaTagIfOpenGraphTagIsAbsent(): void
    {
        $description = $this->faker->sentence;

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="description" content="$description">
                <title>Document</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkDescription(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $description
        ]);
    }

    public function testWillNotUpdateDescriptionIfNoTagIsPresent(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Document</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkDescription(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'description' => $model->description
        ]);
    }
}
