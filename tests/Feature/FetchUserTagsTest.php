<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Repositories\TagRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserTagsTest extends TestCase
{
    protected function FetchUserTagsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('userTags', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/tags', 'userTags');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->FetchUserTagsResponse()->assertUnauthorized();
    }

    public function testFetchUserTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->for($user)->create();

        $tag = TagFactory::new()->create();

        (new TagRepository)->attach([$tag], $bookmark);

        $this->FetchUserTagsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', $tag->name)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'attributes' => ['name']
                    ]
                ],
                'links' => [
                    'first',
                    'prev'
                ],
                'meta' => [
                    'current_page',
                    'path',
                    'per_page',
                    'has_more_pages'
                ]
            ]);
    }

    public function testWillSortByLatest(): void
    {
        // Passport::actingAs($user = UserFactory::new()->create());

        // /** @var Bookmark[] */
        // $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        // $tags = TagFactory::new()->count(2)->create();

        // (new TagRepository)->attach(TagsCollection::make([$tags[0]]), $bookmarks[0]);
        // (new TagRepository)->attach(TagsCollection::make([$tags[1]]), $bookmarks[1]);

        // $this->getTestResponse()
        //     ->assertOk()
        //     ->assertJsonCount(2, 'data')
        //     ->assertJsonPath('data.0.attributes.name', $tags[1]->name)
        //     ->assertJsonPath('data.0.attributes.name', $tags[0]->name);
    }
}
