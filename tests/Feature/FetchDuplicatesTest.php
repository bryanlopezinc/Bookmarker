<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Utils\UrlHasher;
use App\ValueObjects\Url;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\AssertsBookmarkJson;
use Tests\Traits\GeneratesId;

class FetchDuplicatesTest extends TestCase
{
    use WithFaker;
    use AssertsBookmarkJson;
    use AssertValidPaginationData;
    use GeneratesId;

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
        $this->loginUser(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['bookmark_id' => 'foo'])->assertNotFound();

        $this->assertValidPaginationData($this, 'fetchPossibleDuplicates', ['bookmark_id' => 3]);
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['bookmark_id' => BookmarkFactory::new()->create()->public_id->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testDuplicates(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $hash = (new UrlHasher())->hashUrl(new Url($this->faker->url));

        $duplicates = BookmarkFactory::times(2)->for($user)->create(['url_canonical_hash' => $hash]);

        $this->fetchDuplicatesResponse(['bookmark_id' => $duplicates->first()->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $duplicates->last()->public_id->present());
    }

    public function testWillReturnOnlyUserBookmarks(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $hash = (new UrlHasher())->hashUrl(new Url($this->faker->url));

        BookmarkFactory::times(5)->create(['url_canonical_hash' => $hash]);

        $duplicates = BookmarkFactory::times(2)->for($user)->create(['url_canonical_hash' => $hash]);

        $this->fetchDuplicatesResponse(['bookmark_id' => $duplicates->first()->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $duplicates->last()->public_id->present());
    }

    public function testWillReturnNotFoundWhenBookmarkDoesNotExist(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['bookmark_id' => $this->generateBookmarkId()->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
