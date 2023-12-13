<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Tag;
use App\Models\Taggable;
use App\Repositories\TagRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class UpdateBookmarkTest extends TestCase
{
    use WithFaker;
    use WillCheckBookmarksHealth;

    protected function updateBookmarkResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks', 'updateBookmark');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->updateBookmarkResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateBookmarkResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('id');

        $this->updateBookmarkResponse(['id' => 112])
            ->assertJsonValidationErrors([
                'title' => 'The title field is required when none of tags / description are present.'
            ]);

        $this->updateBookmarkResponse([
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

    public function testUpdateBookmark(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->for($user)->create();

        $this->updateBookmarkResponse([
            'id'          => $model->id,
            'title'       => $title = $title = $this->faker->sentence,
            'tags'        => $tag = $this->faker->word,
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
            'taggable_id'   => $model->id,
            'tag_id'        => Tag::query()->where(['name' => $tag])->first()->id
        ]);
    }

    public function testUpdateOnlyTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->for($user)->create();

        $this->updateBookmarkResponse([
            'id'   => $model->id,
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
    }

    public function testUpdateOnlyTitle(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->for($user)->create();

        $this->updateBookmarkResponse([
            'id'    => $model->id,
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

    public function testUpdateOnlyDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->for($user)->create();

        $this->updateBookmarkResponse([
            'id'          => $model->id,
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

    public function testWillReturnBadRequestWhenBookmarkTagsLengthIsExceeded(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->for($user)->create();

        (new TagRepository())->attach(
            TagFactory::times(10)->make()->all(),
            $model
        );

        $this->updateBookmarkResponse([
            'id'   => $model->id,
            'tags' => TagFactory::new()->count(6)->make()->pluck('name')->implode(',')
        ])->assertStatus(400)
            ->assertExactJson(['message' => 'MaxBookmarkTagsLengthExceeded']);
    }

    public function testWillReturnNotFoundResponseWhenBookmarkDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateBookmarkResponse([
            'id'    => BookmarkFactory::new()->create()->id + 1,
            'title' => 'title'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWilReturnNotFoundWhenBookmarkDoesNotBelongToUser(): void
    {
        [$user, $userWithBadIntention] = UserFactory::new()->count(2)->create();
        $userBookmark = BookmarkFactory::new()->for($user)->create();

        Passport::actingAs($userWithBadIntention);
        $this->updateBookmarkResponse([
            'id'    => $userBookmark->id,
            'title' => 'title'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnConflictWhenBookmarkAlreadyHasTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $model = BookmarkFactory::new()->for($user)->create();

        (new TagRepository())->attach(
            $tags = TagFactory::times(10)->make()->pluck('name')->all(),
            $model
        );

        $this->updateBookmarkResponse([
            'id'   => Bookmark::query()->where('user_id', $user->id)->sole('id')->id,
            'tags' => implode(',', [$this->faker->word, $tags[0]]),
        ])->assertStatus(409)
            ->assertExactJson(['message' => 'DuplicateTags']);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->for($user)->create();

        $this->updateBookmarkResponse([
            'id' => $model->id,
            'title' => $this->faker->sentence
        ])->assertOk();

        $this->assertBookmarksHealthWillBeChecked([$model->id]);
    }
}
