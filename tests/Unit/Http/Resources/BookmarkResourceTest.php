<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\Builders\SiteBuilder;
use Tests\TestCase;
use App\Http\Resources\BookmarkResource;
use Database\Factories\BookmarkFactory;
use Database\Factories\SiteFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Tests\Traits\AssertsBookmarkJson;

class BookmarkResourceTest extends TestCase
{
    use WithFaker, AssertsBookmarkJson;

    public function testAttributes(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create())
            ->tags(TagsCollection::createFromStrings($this->faker->words()))
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->isDeadlink(false)
            ->isUserFavourite(false)
            ->build();

        $response = (new BookmarkResource($bookmark))->toResponse(request());

        $testResponse  = (new TestResponse($response))
            ->assertJsonCount(2, 'data')
            ->assertJson(function (AssertableJson $assert) {
                $assert->where('data.attributes.has_tags', true);
                $assert->where('data.attributes.has_description', true);
                $assert->where('data.attributes.is_dead_link', false);
                $assert->where('data.attributes.has_tags', true);
                $assert->has('data.attributes.preview_image_url');
                $assert->has('data.attributes.description');
                $assert->etc();
            })
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertBookmarkJson($testResponse->json('data'));
    }

    public function testWillReturnCorrectDataWhenBookmarkHasNoTags(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create())
            ->tags(TagsCollection::createFromStrings([]))
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->isDeadlink(false)
            ->isUserFavourite(false)
            ->build();

        $response = (new BookmarkResource($bookmark))->toResponse(request());

        (new TestResponse($response))
            ->assertJson(function (AssertableJson $assert) {
                $assert->where('data.attributes.has_tags', false);
                $assert->etc();
            });
    }

    public function testWillReturnCorrectDataWhenBookmarkHasNoDescription(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create([
            'description' => null,
        ]))
            ->tags(TagsCollection::createFromStrings($this->faker->words()))
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->isDeadlink(false)
            ->isUserFavourite(false)
            ->build();

        $response = (new BookmarkResource($bookmark))->toResponse(request());

        (new TestResponse($response))
            ->assertJson(function (AssertableJson $assert) {
                $assert->where('data.attributes.has_description', false);
                $assert->missing('data.attributes.description');
                $assert->etc();
            });
    }

    public function testWillReturnCorrectDataWhenBookmarkHasNoPreviewImage(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create([
            'preview_image_url' => null,
        ]))
            ->tags(TagsCollection::createFromStrings($this->faker->words()))
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->isDeadlink(false)
            ->isUserFavourite(false)
            ->build();

        $response = (new BookmarkResource($bookmark))->toResponse(request());

        (new TestResponse($response))
            ->assertJson(function (AssertableJson $assert) {
                $assert->where('data.attributes.has_preview_image', false);
                $assert->missing('data.attributes.preview_image_url');
                $assert->etc();
            });
    }
}
