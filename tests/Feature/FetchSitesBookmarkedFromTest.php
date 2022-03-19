<?php

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\SiteFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchSitesBookmarkedFromTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserSites', $parameters));
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testSuccessResponse(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $site = SiteFactory::new()->create();

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id,
            'site_id' => $site->id
        ]);

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id
        ]);

        $this->withoutExceptionHandling()
            ->getTestResponse()
            ->assertSuccessful()
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
