<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkTitleWithMetaTag as UpdateBookmarkTitle;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DOMReader;
use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateBookmarkTitleWithMetaTagTest extends TestCase
{
    use WithFaker;

    public function testWillUpdateTitleIfOpenGraphTagIsPresent(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="og:title" content="$title">
                <title>Page Title</title>
            </head>
            <body>

            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkTitle(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $title
        ]);
    }

    public function testWillNotUpdateTitleIfTitleWasSetByUser(): void
    {
        $title = implode(' ', $this->faker->sentences());

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="og:title" content="$title">
                <title>Page Title</title>
            </head>
            <body>

            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create([
            'has_custom_title' => true,
            'title' => $customTitle = $this->faker->word
        ]))->build();

        (new UpdateBookmarkTitle(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $customTitle
        ]);
    }

    public function testWillUpdateTitleWithTitleTagIfOpenGraphTagIsAbsent(): void
    {
        $title = $this->faker->title;

        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>$title</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkTitle(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $title
        ]);
    }

    public function testWillNotUpdateTitleIfBothTagsAreMissing(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkTitle(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'title' => $model->title
        ]);
    }
}
