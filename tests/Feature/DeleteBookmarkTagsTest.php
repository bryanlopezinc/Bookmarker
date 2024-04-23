<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Taggable;
use App\Repositories\TagRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class DeleteBookmarkTagsTest extends TestCase
{
    use WithFaker;
    use GeneratesId;

    protected function deleteBookmarkTagsResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('deleteBookmarkTags', Arr::only($parameters, ['bookmark_id'])),
            $parameters
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks/{bookmark_id}/tags', 'deleteBookmarkTags');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteBookmarkTagsResponse(['bookmark_id' => $this->generateBookmarkId()->present()])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteBookmarkTagsResponse(['bookmark_id' => 3, 'tags' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'BookmarkNotFound']);
    }

    public function testDeleteBookmarkTags(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();
        $tags = TagFactory::new()->count(2)->make([]);

        (new TagRepository())->attach($tags->all(), $bookmark);

        $this->deleteBookmarkTagsResponse(['bookmark_id' => $bookmark->public_id->present(), 'tags' => $tags[0]->name])->assertOk();

        $bookmarkTags = Taggable::query()->where('taggable_id', $bookmark->id)->get();

        $this->assertCount(1, $bookmarkTags);

        $this->assertEquals(
            $bookmarkTags->first()->tag_id,
            Tag::where('name', $tags[1]->name)->sole()->id
        );
    }

    public function testWillReturnNotFoundResponseIfBookmarkDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->deleteBookmarkTagsResponse(['bookmark_id' => $this->generateBookmarkId()->present(), 'tags' => $this->faker->word])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotHaveTags(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->for($user)->create();

        $this->deleteBookmarkTagsResponse([
            'bookmark_id' => $model->public_id->present(),
            'tags'        => $this->faker->word
        ])->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkHasNoSuchTags']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotOwnBookmark(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->deleteBookmarkTagsResponse(['bookmark_id' => $model->public_id->present(), 'tags' => $this->faker->word])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
