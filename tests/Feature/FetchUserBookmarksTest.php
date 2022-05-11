<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesBookmark;

class FetchUserBookmarksTest extends TestCase
{
    use CreatesBookmark;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserBookmarks', $parameters));
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillFetchUserBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id
        ]);

        $this->withoutExceptionHandling()
            ->getTestResponse()
            ->assertSuccessful()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
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

    public function testWillFetchUserBookmarksFromASpecifiedSite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();
        $this->saveBookmark();

        $firstBookmark = Bookmark::query()->where('user_id', $user->id)->first();

        $response =  $this->withoutExceptionHandling()
            ->getTestResponse(['site_id' => $firstBookmark->site_id])
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');

        foreach ($response->json('data') as $resource) {
            $this->assertSame($firstBookmark->site_id, $resource['attributes']['site_id']);
        }
    }

    public function testWillFetchOnlyBookmarksWithAParticularTag(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark(['tags' => $this->faker->words()]);
        $this->saveBookmark([
            'tags' => [$tag = $this->faker->word, $this->faker->word]
        ]);

        $response = $this->withoutExceptionHandling()
            ->getTestResponse(['tag' => $tag])
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');

        $this->assertTrue(in_array($tag, $response->json('data.0.attributes.tags')));
    }

    public function testWillFetchOnlyBookmarksWithoutTags(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->saveBookmark(['tags' => $this->faker->words()]);
        $this->saveBookmark();
        $this->saveBookmark();

        $this->withoutExceptionHandling()
            ->getTestResponse(['untagged' => true])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');
    }

    public function testWillSortBookmarksByOldest(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->saveBookmark();
        $this->saveBookmark();

        $bookmarkIDs = Bookmark::query()->where('user_id', $user->id)->oldest('id')->get('id')->pluck('id')->all();

        $response = $this->withoutExceptionHandling()
            ->getTestResponse(['sort' => 'oldest'])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');

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
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');

        $this->assertTrue($bookmarkIDs === collect($response->json('data'))->pluck('attributes.id')->all());
    }
}
