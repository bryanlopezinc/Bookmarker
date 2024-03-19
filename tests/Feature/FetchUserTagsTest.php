<?php

declare(strict_types=1);

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

        /** @var Bookmark[] */
        $userBookmarks = BookmarkFactory::times(2)->for($user)->create();

        $repository = new TagRepository();

        $repository->attach($tag = TagFactory::new()->create(), $userBookmarks[0]);
        $repository->attach($tag, $userBookmarks[1]);
        $repository->attach($tag, BookmarkFactory::new()->create()); //Not User bookmark

        $this->FetchUserTagsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.name', $tag->name)
            ->assertJsonPath('data.0.attributes.bookmarks_with_tag', 2)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'attributes' => [
                            'name',
                            'bookmarks_with_tag'
                        ]
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
        // $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        // $tags = TagFactory::new()->count(2)->create();

        // $repository = new TagRepository;

        // $repository->attach($tags[0], $bookmarks[0]);
        // $repository->attach($tags[1], $bookmarks[1]);

        // $this->FetchUserTagsResponse()
        //     ->assertOk()
        //     ->assertJsonCount(2, 'data')
        //     ->assertJsonPath('data.0.attributes.name', $tags[1]->name)
        //     ->assertJsonPath('data.0.attributes.name', $tags[0]->name);
    }
}
