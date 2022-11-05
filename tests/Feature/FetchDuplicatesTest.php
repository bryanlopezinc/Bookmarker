<?php

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
    use WithFaker, AssertsBookmarkJson;

    protected function fetchDuplicatesResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchPossibleDuplicates', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks/duplicates', 'fetchPossibleDuplicates');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->fetchDuplicatesResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttributesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse()->assertJsonValidationErrorFor('id');
    }

    public function testWillThrowValidationWhenAttributesAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['id' => 'foo bar'])->assertJsonValidationErrorFor('id');
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->fetchDuplicatesResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->fetchDuplicatesResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->fetchDuplicatesResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testBookmarkMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse(['id' => BookmarkFactory::new()->create()->id])->assertForbidden();
    }

    public function testWillReturnDuplicates(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userBookmarks = BookmarkFactory::new()->count(5)->create([
            'url_canonical_hash' => (new UrlHasher)->hashUrl(new Url($this->faker->url)),
            'user_id' => $user->id
        ])->pluck('id');

        $this->fetchDuplicatesResponse(['id' => $userBookmarks->first()])
            ->assertOk()
            ->assertJsonCount(4, 'data')
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
            ])
            ->collect('data')
            ->each(function (array $data) use ($userBookmarks) {
                $this->assertBookmarkJson($data);
                $this->assertNotEquals($data['attributes']['id'], $userBookmarks->first());
            });
    }

    public function testWillReturnOnlyUserBookmarks(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $hash = (new UrlHasher)->hashUrl(new Url($this->faker->url));

        BookmarkFactory::times(5)->create(['url_canonical_hash' => $hash]);

        $userBookmarks = BookmarkFactory::new()->count(3)->create([
            'url_canonical_hash' => $hash,
            'user_id' => $user->id
        ])->pluck('id');

        $this->fetchDuplicatesResponse(['id' => $userBookmarks->first()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->collect('data')
            ->each(function (array $data) use ($userBookmarks) {
                $this->assertContains($data['attributes']['id'], $userBookmarks);
            });
    }

    public function testWhenBookmarkDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchDuplicatesResponse([
            'id' => BookmarkFactory::new()->create()->id + 1
        ])->assertNotFound();
    }
}
