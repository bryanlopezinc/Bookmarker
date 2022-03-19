<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateSiteNameWithMetaTag as UpdateSiteName;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Models\WebSite;
use Database\Factories\SiteFactory;
use Tests\TestCase;

class UpdateSiteNameWithMetaTagTest extends TestCase
{
    public function testWillUpdateNameIfOpenGraphTagIsPresent(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="og:site_name" content="PlayStation">
                <meta name="application-name" content="Xbox">
                <title>Document</title>
            </head>
            <body>

            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create())->build())
            ->build();

        $document = new \DOMDocument();
        $document->loadHTML($html);

        (new UpdateSiteName($document))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => 'PlayStation'
        ]);
    }

    public function testWillUseApplicationNameMetaTagIfNoOpenGraphTag(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="application-name" content="Xbox">
                <title>Document</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create())->build())
            ->build();

        $document = new \DOMDocument();
        $document->loadHTML($html);

        (new UpdateSiteName($document))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => 'Xbox'
        ]);
    }

    public function testWillNotUpdateNameIfNoTagIsPresent(): void
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

        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create())->build())
            ->build();

        $document = new \DOMDocument();
        $document->loadHTML($html);

        (new UpdateSiteName($document))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => $site->name
        ]);
    }

    public function testWillNotUpdateNameIfNameHasBeenUpdated(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="og:site_name" content="PlayStation">
                <title>Document</title>
            </head>
            <body>
            </body>
            </html>
        HTML;

        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create(['name_updated_at' => now(), 'name' => 'foosite']))->build())
            ->build();

        $document = new \DOMDocument();
        $document->loadHTML($html);

        (new UpdateSiteName($document))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => 'foosite'
        ]);
    }
}
