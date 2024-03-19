<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Utils\UrlHasher;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\AssertsBookmarkJson;

class FetchDuplicatesTest extends TestCase
{
    use WithFaker;
    use AssertsBookmarkJson;
    use AssertValidPaginationData;

    protected function fetchDuplicatesResponse(array $parameters = []): TestResponse
    {
        $bookmarkId = $parameters['bookmark_id'];

        unset($parameters['bookmark_id']);

        return $this->getJson(route('fetchPossibleDuplicates', [...$parameters, 'bookmark_id' => $bookmarkId]));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/bookmarks/{bookmark_id}/duplicates', 'fetchPossibleDuplicates');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchDuplicatesResponse(['bookmark_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchPossibleDuplicates', ['bookmark_id' => 3]);
    }

    public function testWillReturnNotFoundWhenBookmarkIdIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['bookmark_id' => 'foo'])->assertNotFound();
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['bookmark_id' => BookmarkFactory::new()->create()->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testDuplicates(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $hash = (new UrlHasher())->hashUrl(new Url($this->faker->url));

        $duplicates = BookmarkFactory::times(2)->for($user)->create(['url_canonical_hash' => $hash]);

        $this->fetchDuplicatesResponse(['bookmark_id' => $duplicates->first()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $duplicates->last()->id);
    }

    public function testWillReturnOnlyUserBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $hash = (new UrlHasher())->hashUrl(new Url($this->faker->url));

        BookmarkFactory::times(5)->create(['url_canonical_hash' => $hash]);

        $duplicates = BookmarkFactory::times(2)->for($user)->create(['url_canonical_hash' => $hash])->pluck('id');

        $this->fetchDuplicatesResponse(['bookmark_id' => $duplicates->first()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $duplicates->last());
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['bookmark_id' => BookmarkFactory::new()->create()->id + 1])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
