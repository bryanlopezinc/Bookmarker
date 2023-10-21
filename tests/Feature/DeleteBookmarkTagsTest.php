<?php

namespace Tests\Feature;

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

class DeleteBookmarkTagsTest extends TestCase
{
    use WithFaker;

    protected function deleteBookmarkTagsResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmarkTags'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks/tags/remove', 'deleteBookmarkTags');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteBookmarkTagsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->deleteBookmarkTagsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('id')
            ->assertJsonValidationErrorFor('tags');
    }

    public function testDeleteBookmarkTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();
        $tags = TagFactory::new()->count(2)->make([]);

        (new TagRepository)->attach($tags->all(), $bookmark);

        $this->deleteBookmarkTagsResponse(['id' => $bookmark->id, 'tags' => $tags[0]->name])->assertOk();

        $bookmarkTags = Taggable::query()->where('taggable_id', $bookmark->id)->get();

        $this->assertCount(1, $bookmarkTags);

        $this->assertEquals(
            $bookmarkTags->first()->tag_id,
            Tag::where('name', $tags[1]->name)->sole()->id
        );
    }

    public function testWillReturnNotFoundResponseIfBookmarkDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->for($user)->create();

        $this->deleteBookmarkTagsResponse(['id' => $model->id + 1, 'tags' => $this->faker->word])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotHaveTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->for($user)->create();

        $this->deleteBookmarkTagsResponse([
            'id'   => $model->id,
            'tags' => $this->faker->word
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkHasNoSuchTags']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->deleteBookmarkTagsResponse(['id' => $model->id, 'tags' => $this->faker->word])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
