<?php

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\SourceFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserBookmarksSourcesTest extends TestCase
{
    protected function userBookmarksSourcesResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserBookmarksSources', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/bookmarks/sources', 'fetchUserBookmarksSources');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->userBookmarksSourcesResponse()->assertUnauthorized();
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userBookmarksSourcesResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->userBookmarksSourcesResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 50.']
            ]);

        $this->userBookmarksSourcesResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->userBookmarksSourcesResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testSuccessResponse(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $source = SourceFactory::new()->create();

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id,
            'source_id' => $source->id
        ]);

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ]);

        $this->withoutExceptionHandling()
            ->userBookmarksSourcesResponse()
            ->assertOk()
            ->assertJsonCount(6, 'data')
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
}
