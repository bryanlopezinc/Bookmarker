<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favorite;
use App\Repositories\TagRepository;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use Database\Factories\SourceFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\{AssertsBookmarkJson, WillCheckBookmarksHealth};

class FetchUserBookmarksTest extends TestCase
{
    use WithFaker,
    AssertsBookmarkJson,
    WillCheckBookmarksHealth,
    AssertValidPaginationData;

    protected function userBookmarksResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/bookmarks', 'fetchUserBookmarks');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->userBookmarksResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserBookmarks');

          $this->userBookmarksResponse([
            'tags' => collect()->times(16, fn () => $this->faker->word)->implode(',')
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tags' => ['The tags must not be greater than 15 characters.']
            ]);

        $this->userBookmarksResponse(['source_id' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('source_id');

        $this->userBookmarksResponse(['tags' => str_repeat('H', 23)])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('tags.0');
        }

    public function testWillFetchUserBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->userBookmarksResponse([])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'links')
            ->assertJsonCount(4, 'meta')
            ->assertJsonPath('data.0.attributes.id', $bookmark->id)
            ->assertJsonPath('data.0.attributes.has_tags', false)
            ->assertJsonPath('data.0.attributes.tags_count', 0)
            ->assertJsonPath('data.0.attributes.title', $bookmark->title)
            ->assertJsonPath('data.0.attributes.description', $bookmark->description)
            ->assertJsonPath('data.0.attributes.is_user_favorite', false)
            ->assertJsonCount(2, 'links')
            ->assertJsonCount(4, 'meta')
            ->assertJsonStructure([
                'data',
                "links" => [
                    "first",
                    "prev",
                ],
                "meta" => [
                    "current_page",
                    "path",
                    "per_page",
                    "has_more_pages",
                ]
            ]);
    }

    public function testWillSortBookmarksByLatestByDefault(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $this->userBookmarksResponse([])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.attributes.id', $bookmarks[1]->id)
            ->assertJsonPath('data.0.attributes.id', $bookmarks[0]->id);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->for($user)->count(10)->create();

        $this->userBookmarksResponse([])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillFilterUserBookmarksFromASpecifiedSource(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $factory = BookmarkFactory::new()->for($user);
        $source = SourceFactory::new()->create();

        $factory->create();

        /** @var Bookmark */
        $expected = $factory->create(['source_id' => $source->id]);

        $this->userBookmarksResponse(['source_id' => (string) $expected->source_id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $expected->id);
    }

    public function testWillFetchOnlyBookmarksWithAParticularTag(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $factory = BookmarkFactory::new()->for($user);
        $tag = TagFactory::new()->create();

        $factory->create();

        /** @var Bookmark */
        $expected = $factory->create();

        (new TagRepository)->attach([$tag], $expected);

        $this->userBookmarksResponse(['tags' => $tag->name])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $expected->id);
    }

    public function testWillFetchOnlyBookmarksWithoutTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $factory = BookmarkFactory::new()->for($user);
        $tag = TagFactory::new()->create();

        /** @var Bookmark */
        $bookmarkWithTag = $factory->create();

        /** @var Bookmark */
        $expected = $factory->create();

        (new TagRepository)->attach([$tag], $bookmarkWithTag);

        $this->userBookmarksResponse(['untagged' => true])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $expected->id);
    }

    public function testWillSortBookmarksByOldest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $this->userBookmarksResponse(['sort' => 'oldest'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[0]->id)
            ->assertJsonPath('data.1.attributes.id', $bookmarks[1]->id);
    }

    public function testWillSortBookmarksByLatest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        $this->userBookmarksResponse(['sort' => 'newest'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[1]->id)
            ->assertJsonPath('data.1.attributes.id', $bookmarks[0]->id);
    }

    public function test_is_user_favorite_attribute_will_be_true(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        Favorite::create([
            'user_id'     => $user->id,
            'bookmark_id' => $bookmarks[0]->id,
            'created_at'  => now(),
        ]);

        $this->userBookmarksResponse()
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.is_user_favorite', true)
            ->assertJsonPath('data.1.attributes.is_user_favorite', false);
    }

    public function testWillFetchOnlyBookmarksWithDeadLinks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        /** @var Bookmark[] */
        $bookmarks = BookmarkFactory::new()->count(2)->for($user)->create();

        BookmarkHealthFactory::new()->unHealthy()->create([
            'bookmark_id' => $bookmarks[0]->id,
        ]);

        $this->userBookmarksResponse(['dead_links' => true])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $bookmarks[0]->id);
    }

    public function testWhenBookmarkHasTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $tags = TagFactory::new()->count(2)->create();

        /** @var Bookmark */
        $bookmark = BookmarkFactory::new()->for($user)->create();

        (new TagRepository)->attach($tags->all(), $bookmark);

        $this->userBookmarksResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.tags', function (array $bookmarkTags) use ($tags) {
                $this->assertEquals(
                    $tags->pluck('name')->sortDesc()->values()->all(),
                    collect($bookmarkTags)->sortDesc()->values()->all()
                );

                return true;
            });
    }
}
