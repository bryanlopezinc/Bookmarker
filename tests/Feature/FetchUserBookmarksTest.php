<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\BookmarkHealthFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\{AssertsBookmarkJson, CreatesBookmark, AssertsBookmarksWillBeHealthchecked};

class FetchUserBookmarksTest extends TestCase
{
    use CreatesBookmark, AssertsBookmarkJson, AssertsBookmarksWillBeHealthchecked;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserBookmarks', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/users/bookmarks', 'fetchUserBookmarks');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['per_page', 'page'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page field must have a value.'],
                'page' => ['The page field must have a value.'],
            ]);

        $this->getTestResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->getTestResponse(['per_page' => 40])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->getTestResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->getTestResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testCannotsSearchMoreThan15Tags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse([
            'tags' => collect()->times(16, fn () => $this->faker->word)->implode(',')
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tags' => ['The tags must not be greater than 15 characters.']
            ]);
    }

    public function testWillFetchUserBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
            'title' => '<h1>did you forget something?</h1>',
            'description' => 'And <h1>spoof!</h1>'
        ]);

        $this->getTestResponse([])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson(function (AssertableJson $json) {
                $json->etc()
                    ->where('links.first', route('fetchUserBookmarks', ['per_page' => 15, 'page' => 1]))
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) {
                        $this->assertBookmarkJson($json->toArray());
                        //Assert sanitized attributes was sent to client.
                        $json->where('attributes.title', '&lt;h1&gt;did you forget something?&lt;/h1&gt;');
                        $json->where('attributes.description', 'And &lt;h1&gt;spoof!&lt;/h1&gt;');
                        $json->where('attributes.is_user_favourite', false)->etc();
                    });
            })
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

        $this->getTestResponse(['per_page' => 20])
            ->assertSuccessful()
            ->assertJson(function (AssertableJson $json) {
                $json->where('links.first', route('fetchUserBookmarks', ['per_page' => 20, 'page' => 1]))->etc();
            });
    }

    public function testWillSortBookmarksByLatestByDefault(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $bookmarkIDs = Bookmark::query()->where('user_id', $user->id)->latest('id')->get('id')->pluck('id')->all();

        $response = $this->withoutExceptionHandling()->getTestResponse([])->assertOk();

        $this->assertEquals($bookmarkIDs, collect($response->json('data'))->pluck('attributes.id')->all());
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(10)->create(['user_id' => $user->id]);

        $this->getTestResponse([])->assertOk();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillFetchUserBookmarksFromASpecifiedSite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();
        $this->saveBookmark();

        $firstBookmark = Bookmark::query()->where('user_id', $user->id)->first();

        $response =  $this->getTestResponse(['site_id' => $firstBookmark->site_id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson(function (AssertableJson $assert) use ($firstBookmark) {
                $link = route('fetchUserBookmarks', [
                    'per_page' => 15,
                    'site_id' => $firstBookmark->site_id,
                    'page' => 1,
                ]);

                $assert->where('links.first', $link)->etc();
            });

        foreach ($response->json('data') as $resource) {
            $this->assertSame($firstBookmark->site_id, data_get($resource, 'attributes.from_site.attributes.id'));
        }
    }

    public function testWillFetchOnlyBookmarksWithAParticularTag(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark(['tags' => TagFactory::new()->count(3)->make()->pluck('name')->all()]);
        $this->saveBookmark([
            'tags' => [
                $tag = TagFactory::new()->make()->name,
                TagFactory::new()->make()->name
            ]
        ]);

        $response = $this->getTestResponse(['tags' => $tag])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson(function (AssertableJson $assert) use ($tag) {
                $link = route('fetchUserBookmarks', [
                    'per_page' => 15,
                    'tags' => $tag,
                    'page' => 1,
                ]);

                $assert->where('links.first', $link)->etc();
            });

        $this->assertTrue(in_array($tag, $response->json('data.0.attributes.tags')));
    }

    public function testWillFetchBookmarksWithParticularTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $tags = TagFactory::new()->count(3)->make()->pluck('name')->all();

        $this->saveBookmark(); //unrelated to tag group
        $this->saveBookmark(['tags' => [$this->faker->word . '1']]); //unrelated to tag group
        $this->saveBookmark(['tags' => [$tags[0], $this->faker->word]]); // related to tag group but with extra tag
        $this->saveBookmark(['tags' => [$tags[1], $this->faker->word]]);
        $this->saveBookmark(['tags' => [$tags[2], $this->faker->word]]);
        $this->saveBookmark(['tags' => [$tags[0]]]); // related to tag group
        $this->saveBookmark(['tags' => [$tags[1]]]);
        $this->saveBookmark(['tags' => [$tags[2]]]);

        $response = $this->getTestResponse([
            'tags' => implode(',', $tags),
            'sort' => 'oldest',
        ])
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJson(function (AssertableJson $json) use ($tags) {
                $link = route('fetchUserBookmarks', [
                    'per_page' => 15,
                    'tags' => implode(',', $tags),
                    'sort' => 'oldest',
                    'page' => 1,
                ]);

                $json->where('links.first', $link);
                $json->where('links.prev', $link);
                $json->etc();
            });

        $this->assertContains($tags[0], $response->json('data.0.attributes.tags'));
        $this->assertContains($tags[1], $response->json('data.1.attributes.tags'));
        $this->assertContains($tags[2], $response->json('data.2.attributes.tags'));
        $this->assertContains($tags[0], $response->json('data.3.attributes.tags'));
        $this->assertContains($tags[1], $response->json('data.4.attributes.tags'));
        $this->assertContains($tags[2], $response->json('data.5.attributes.tags'));
    }

    public function testWillFetchOnlyBookmarksWithoutTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark(['tags' => TagFactory::new()->count(3)->make()->pluck('name')->all()]);
        $this->saveBookmark();
        $this->saveBookmark();

        $this->getTestResponse(['untagged' => true])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJson(function (AssertableJson $assert) {
                $link = route('fetchUserBookmarks', [
                    'per_page' => 15,
                    'untagged' => 1,
                    'page' => 1,
                ]);

                $assert->where('links.first', $link)->etc();
            });
    }

    public function testWillSortBookmarksByOldest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $bookmarkIDs = Bookmark::query()->where('user_id', $user->id)->oldest('id')->get('id')->pluck('id')->all();

        $response = $this->withoutExceptionHandling()
            ->getTestResponse(['sort' => 'oldest'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJson(function (AssertableJson $assert) {
                $link = route('fetchUserBookmarks', [
                    'per_page' => 15,
                    'sort' => 'oldest',
                    'page' => 1,
                ]);

                $assert->where('links.first', $link)->etc();
            });

        $this->assertTrue($bookmarkIDs === collect($response->json('data'))->pluck('attributes.id')->all());
    }

    public function testWillSortBookmarksByLatest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $bookmarkIDs = Bookmark::query()->where('user_id', $user->id)->latest('id')->get('id')->pluck('id')->all();

        $response = $this->withoutExceptionHandling()
            ->getTestResponse(['sort' => 'newest'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJson(function (AssertableJson $assert) {
                $link = route('fetchUserBookmarks', [
                    'per_page' => 15,
                    'sort' => 'newest',
                    'page' => 1,
                ]);

                $assert->where('links.first', $link)->etc();
            });


        $this->assertTrue($bookmarkIDs === collect($response->json('data'))->pluck('attributes.id')->all());
    }

    public function testPaginateResponse(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        for ($i = 0; $i < 20; $i++) {
            $this->saveBookmark();
        }

        $this->getTestResponse(['per_page' => 17])
            ->assertOk()
            ->assertJsonCount(17, 'data');
    }

    public function test_is_user_favourite_attribute_will_be_true(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ]);

        $userfavourite = $bookmarks->random();

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $userfavourite->id])->assertCreated();

        $this->getTestResponse([])
            ->assertOk()
            ->assertJson(function (AssertableJson $json) use ($userfavourite) {
                $json->etc()
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($userfavourite) {
                        $json->where('attributes.is_user_favourite', function (bool $value) use ($userfavourite, $json) {
                            if ($json->toArray()['attributes']['id'] === $userfavourite->id) {
                                $this->assertTrue($value);
                            } else {
                                $this->assertFalse($value);
                            }

                            return true;
                        })
                            ->etc();
                    });
            });
    }

    public function testWillFetchOnlyBookmarksWithDeadLinks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();
        $this->saveBookmark();

        $userBookmarks = Bookmark::query()->where('user_id', $user->id)->get();

        $bookmarkWithDeadLink = $userBookmarks->last();

        BookmarkHealthFactory::new()->unHealthy()->create([
            'bookmark_id' => $bookmarkWithDeadLink->id,
        ]);

        $this->getTestResponse(['dead_links' => true])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson(function (AssertableJson $assert) use ($bookmarkWithDeadLink) {
                $assert->where('data.0.attributes.id', $bookmarkWithDeadLink->id)->etc();
            });
    }
}
