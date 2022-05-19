<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkTag;
use App\Models\Tag;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UpdateBookmarkTest extends TestCase
{
    use WithFaker;

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
            'title' => 'The title field is required when none of tags are present.'
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
            'tags' => $tag = $this->faker->word,
            'title' => $title = $this->faker->sentence
        ])->assertSuccessful();

        $this->assertDatabaseHas(Bookmark::class, [
            'id' => $model->id,
            'title' => $title
        ]);

        $this->assertDatabaseHas(BookmarkTag::class, [
            'bookmark_id' => $model->id,
            'tag_id' => Tag::query()->where('name', $tag)->first()->id
        ]);
    }

    public function testCanUpdateBookmarkOnlyTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $this->faker->word,
        ])->assertSuccessful();
    }

    public function testCanUpdateBookmarkOnlyTitle(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'title' => $this->faker->sentence
        ])->assertSuccessful();
    }

    public function testWillReturnBadRequestResponseWhenBookmarkTagsLengthIsExceeded(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        TagFactory::new()->count(10)->create()->tap(function (Collection $collection) use ($model) {
            BookmarkTag::insert($collection->map(fn (Tag $tag) => [
                'bookmark_id' => $model->id,
                'tag_id' => $tag->id
            ])->all());
        });

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => implode(',', $this->faker->words(6))
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
}
