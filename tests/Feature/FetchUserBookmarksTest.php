<?php

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\SiteFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserBookmarksTest extends TestCase
{
    use WithFaker;

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

        $site = SiteFactory::new()->create();

        BookmarkFactory::new()->count(5)->create([
            'user_id' => $user->id,
            'site_id' => $site->id
        ]);

        BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
        ]);

        $response =  $this->withoutExceptionHandling()
            ->getTestResponse(['site_id' => $site->id])
            ->assertSuccessful()
            ->assertJsonCount(5, 'data');

        foreach ($response->json('data') as $resource) {
            $this->assertSame($site->id, $resource['attributes']['site_id']);
        }
    }

    public function testWillFetchOnlyBookmarksWithAParticularTag(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        BookmarkFactory::new()->count(10)->create([
            'user_id' => $user->id,
        ]);

        $this->withoutExceptionHandling()
            ->getTestResponse(['tag' => $this->faker->word])
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');
    }
}
