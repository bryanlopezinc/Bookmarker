<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Tag;
use App\Models\Taggable;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;
use Tests\Traits\CreatesBookmark;

class UpdateBookmarkTest extends TestCase
{
    use WithFaker, WillCheckBookmarksHealth, CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks', 'updateBookmark');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationExceptionWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('id');
    }

    public function testRequiresOneAttributeToBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'id' => 112
        ])->assertJsonValidationErrors([
            'title' => 'The title field is required when none of tags / description are present.'
        ]);
    }

    public function testWillUpdateBookmark(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'title' => $title = $title = $this->faker->sentence,
            'tags' => $tag = $this->faker->word,
            'description' => $description = $this->faker->sentence
        ])->assertOk();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->whereKey($model->id)->sole();

        $this->assertEquals($title, $bookmark->title);
        $this->assertTrue($bookmark->has_custom_title);
        $this->assertEquals($description, $bookmark->description);
        $this->assertTrue($bookmark->description_set_by_user);
        $this->assertEquals($model->preview_image_url, $bookmark->preview_image_url);
        $this->assertEquals($model->url, $bookmark->url);
        $this->assertEquals($model->source_id, $bookmark->source_id);
        $this->assertEquals($model->created_at->timestamp, $bookmark->created_at->timestamp);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $model->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => Tag::query()->where(['name' => $tag, 'created_by' => $user->id])->first()->id
        ]);
    }

    public function testCanUpdateOnlyTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $tag = $this->faker->word,
        ])->assertOk();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->whereKey($model->id)->sole();

        $this->assertEquals($model->title, $bookmark->title);
        $this->assertFalse($bookmark->has_custom_title);
        $this->assertEquals($model->description, $bookmark->description);
        $this->assertFalse($bookmark->description_set_by_user);
        $this->assertEquals($model->preview_image_url, $bookmark->preview_image_url);
        $this->assertEquals($model->url, $bookmark->url);
        $this->assertEquals($model->source_id, $bookmark->source_id);
        $this->assertEquals($model->created_at->timestamp, $bookmark->created_at->timestamp);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $model->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => Tag::query()->where([
                'name' => $tag,
                'created_by' => $user->id,
            ])->first()->id
        ]);
    }

    public function testCanUpdateOnlyTitle(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'title' => $title = $this->faker->sentence
        ])->assertOk();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->whereKey($model->id)->sole();

        $this->assertEquals($title, $bookmark->title);
        $this->assertTrue($bookmark->has_custom_title);
        $this->assertEquals($model->description, $bookmark->description);
        $this->assertFalse($bookmark->description_set_by_user);
        $this->assertEquals($model->preview_image_url, $bookmark->preview_image_url);
        $this->assertEquals($model->url, $bookmark->url);
        $this->assertEquals($model->source_id, $bookmark->source_id);
        $this->assertEquals($model->created_at->timestamp, $bookmark->created_at->timestamp);
    }

    public function testCanUpdateOnlyDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'description' => $description = $this->faker->sentence
        ])->assertOk();

        /** @var Bookmark */
        $bookmark = Bookmark::query()->whereKey($model->id)->sole();

        $this->assertEquals($model->title, $bookmark->title);
        $this->assertFalse($bookmark->has_custom_title);
        $this->assertEquals($description, $bookmark->description);
        $this->assertTrue($bookmark->description_set_by_user);
        $this->assertEquals($model->preview_image_url, $bookmark->preview_image_url);
        $this->assertEquals($model->url, $bookmark->url);
        $this->assertEquals($model->source_id, $bookmark->source_id);
        $this->assertEquals($model->created_at->timestamp, $bookmark->created_at->timestamp);
    }

    public function testWillReturnBadRequestResponseWhenBookmarkTagsLengthIsExceeded(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        TagFactory::new()
            ->count(10)
            ->create(['created_by' => $model->user_id])
            ->tap(function (Collection $collection) use ($model) {
                Taggable::insert($collection->map(fn (Tag $tag) => [
                    'taggable_id' => $model->id,
                    'taggable_type' => Taggable::BOOKMARK_TYPE,
                    'tag_id' => $tag->id,
                ])->all());
            });

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => TagFactory::new()->count(6)->make()->pluck('name')->implode(',')
        ])->assertStatus(400);
    }

    public function testWillReturnNotFoundResponseWhenBookmarkDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->getTestResponse([
            'id' => $model->id + 1,
            'title' => 'title'
        ])->assertNotFound();
    }

    public function testWillReturnForbiddenResponseWheUserDidNotCreateBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->getTestResponse([
            'id' => $model->id,
            'title' => 'title'
        ])->assertForbidden();
    }

    public function testCannotAttachExistingTagToBookmark(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark(['tags' => $tags = TagFactory::new()->count(3)->make()->pluck('name')->all()]);

        shuffle($tags);

        $this->getTestResponse([
            'id' => Bookmark::query()->where('user_id', $user->id)->sole('id')->id,
            'tags' => $tags[0],
        ])->assertStatus(409)
            ->assertExactJson(['message' =>  'Duplicate tags']);
    }

    public function testTagsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'tags' => 'howTo,howTo,stackOverflow'
        ])->assertJsonValidationErrors([
            "tags.0" => [
                "The tags.0 field has a duplicate value."
            ],
            "tags.1" => [
                "The tags.1 field has a duplicate value."
            ]
        ]);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'title' => $this->faker->sentence
        ])->assertOk();

        $this->assertBookmarksHealthWillBeChecked([$model->id]);
    }
}
