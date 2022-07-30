<?php

namespace Tests\Feature;

use App\Models\Taggable;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteBookmarkTagsTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmarkTags'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/bookmarks/tags/remove', 'deleteBookmarkTags');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('id')->assertJsonValidationErrorFor('tags');
    }

    public function testWillDeleteBookmarkTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $tag = TagFactory::new()->create(['created_by' => $model->user_id]);

        Taggable::create($tagAttributes = [
            'taggable_id' => $model->id,
            'tag_id' => $tag->id,
            'taggable_type' => Taggable::BOOKMARK_TYPE
        ]);

        $this->getTestResponse(['id' => $model->id, 'tags' => $tag->name])->assertOk();
        $this->assertDatabaseMissing(Taggable::class, $tagAttributes);
    }

    public function testWillReturnNotFoundResponseIfBookmarkDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['id' => $model->id + 1, 'tags' => $this->faker->word])->assertNotFound();
    }

    public function testWillReturnSuccessIfBookmarkDoesNotHaveTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([ 'user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $this->faker->word
        ])->assertOk();
    }

    public function testWillReturnForbiddenWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->getTestResponse(['id' => $model->id, 'tags' => $this->faker->word])->assertForbidden();
    }
}
