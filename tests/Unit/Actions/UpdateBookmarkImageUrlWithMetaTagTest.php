<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookmarkImageUrlWithMetaTag as UpdateBookmarkImage;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DOMReader;
use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Tests\TestCase;

class UpdateBookmarkImageUrlWithMetaTagTest extends TestCase
{
    public function testWillUpdateImageUrlIfMetaTagIsPresent(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta property="og:image" content="https://image.com/smike.png">
                <title>Document</title>
            </head>
            <body>

            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkImage(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'preview_image_url' => 'https://image.com/smike.png'
        ]);
    }

    public function testWillNotUpdateImageUrlIfMetaTagIsAbsent(): void
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

        (new UpdateBookmarkImage(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'preview_image_url' => $model->preview_image_url
        ]);
    }

    public function testWillNotUpdateImageUrlIfOpenGraphTagContentIsInvalid(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta property="og:image" content="<script> alert('hacked') </script>">
                <title>Document</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::fromModel($model = BookmarkFactory::new()->create())->build();

        (new UpdateBookmarkImage(new DOMReader($html)))($bookmark);

        $this->assertDatabaseHas(Bookmark::class, [
            'id'   => $model->id,
            'preview_image_url' => $model->preview_image_url
        ]);
    }
}
