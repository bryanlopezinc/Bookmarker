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
use Tests\Traits\AssertsBookmarksWillBeHealthchecked;
use Tests\Traits\CreatesBookmark;

class UpdateBookmarkTest extends TestCase
{
    use WithFaker, AssertsBookmarksWillBeHealthchecked, CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks', 'updateBookmark');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationExceptionWhenRequiredAttrbutesAreMissing(): void
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

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'title' => $title = $title = $this->faker->sentence,
            'tags' => $tag = $this->faker->word,
            'description' => $description = $this->faker->sentence
        ])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'title' => $title,
            'has_custom_title' => true,
            'description' => $description,
            'description_set_by_user' => true,
        ]);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $model->id,
            'tagged_by_id' => $user->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => Tag::query()->where('name', $tag)->first()->id
        ]);
    }

    public function testCanUpdateOnlyTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $tag = $this->faker->word,
        ])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'title' => $model->title,
            'has_custom_title' => false,
            'description' => $model->description,
            'description_set_by_user' => false,
        ]);

        $this->assertDatabaseHas(Taggable::class, [
            'taggable_id' => $model->id,
            'tagged_by_id' => $user->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE,
            'tag_id' => Tag::query()->where('name', $tag)->first()->id
        ]);
    }

    public function testCanUpdateOnlyTitle(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'title' => $title = $this->faker->sentence
        ])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'title' => $title,
            'has_custom_title' => true,
            'description' => $model->description,
            'description_set_by_user' => false,
        ]);
    }

    public function testCanUpdateOnlyDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'description' => $description = $this->faker->sentence
        ])->assertOk();

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'title' => $model->title,
            'has_custom_title' => false,
            'description' => $description,
            'description_set_by_user' => true,
        ]);
    }

    public function testWillReturnBadRequestResponseWhenBookmarkTagsLengthIsExceeded(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        TagFactory::new()->count(10)->create()->tap(function (Collection $collection) use ($model) {
            Taggable::insert($collection->map(fn (Tag $tag) => [
                'taggable_id' => $model->id,
                'taggable_type' => Taggable::BOOKMARK_TYPE,
                'tag_id' => $tag->id,
                'tagged_by_id' => $model->user_id
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
