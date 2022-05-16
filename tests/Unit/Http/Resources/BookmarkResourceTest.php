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

class BookmarkResourceTest extends TestCase
{
    use WithFaker;

    public function testAttributes(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create())
            ->tags(TagsCollection::createFromStrings($this->faker->words()))
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->isDeadlink(false)
            ->build();

        $response = (new BookmarkResource($bookmark))->toResponse(request());

        (new TestResponse($response))
            ->assertJsonCount(14, 'data.attributes')
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(3, 'data.attributes.created_on')
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
                'data' => [
                    'type',
                    'attributes' => [
                        'id',
                        'title',
                        'web_page_link',
                        'has_preview_image',
                        'preview_image_url',
                        'description',
                        'has_description',
                        'site_id',
                        'from_site',
                        'tags',
                        'has_tags',
                        'tags_count',
                        'is_dead_link',
                        'created_on' => [
                            'date_readable',
                            'date_time',
                            'date',
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnCorrectDataWhenBookmarkHasNoTags(): void
    {
        $bookmark = BookmarkBuilder::fromModel(BookmarkFactory::new()->create())
            ->tags(TagsCollection::createFromStrings([]))
            ->site(SiteBuilder::fromModel(SiteFactory::new()->create())->build())
            ->isDeadlink(false)
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
