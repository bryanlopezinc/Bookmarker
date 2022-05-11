<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateSiteNameWithMetaTag as UpdateSiteName;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use App\Models\WebSite;
use App\Readers\WebPageData;
use App\ValueObjects\Url;
use Database\Factories\SiteFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateSiteNameWithMetaTagTest extends TestCase
{
    use WithFaker;
    
    public function testWillUpdateName(): void
    {
        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create())->build())
            ->build();

        $data = WebPageData::fromArray([
            'siteName' => 'PlayStation',
            'description' => implode(' ', $this->faker->sentences()),
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
        ]);

        (new UpdateSiteName($data))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => 'PlayStation'
        ]);
    }

    public function testWillNotUpdateName(): void
    {
        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create())->build())
            ->build();

        $data = WebPageData::fromArray([
            'siteName' => false,
            'description' => implode(' ', $this->faker->sentences()),
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
        ]);

        (new UpdateSiteName($data))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => $site->name
        ]);
    }

    public function testWillNotUpdateNameIfNameHasBeenUpdated(): void
    {
        $bookmark = BookmarkBuilder::new()
            ->site(SiteBuilder::fromModel($site = SiteFactory::new()->create(['name_updated_at' => now(), 'name' => 'foosite']))->build())
            ->build();

        $data = WebPageData::fromArray([
            'siteName' => 'PlayStation',
            'description' => implode(' ', $this->faker->sentences()),
            'imageUrl' => new Url($this->faker->url),
            'title' => $this->faker->sentence,
        ]);

        (new UpdateSiteName($data))($bookmark);

        $this->assertDatabaseHas(WebSite::class, [
            'id'   => $site->id,
            'name' => 'foosite'
        ]);
    }
}
