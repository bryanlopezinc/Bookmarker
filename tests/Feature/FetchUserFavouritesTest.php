<?php

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\AssertsBookmarkJson;

class FetchUserFavouritesTest extends TestCase
{
    use AssertsBookmarkJson;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserFavourites', $parameters));
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenPaginationDataIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);

        $this->getTestResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->getTestResponse(['per_page' => 14])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);;

        $this->getTestResponse(['per_page' => 40])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);
    }

    public function testWillFetchUserFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse()->assertSuccessful()->assertJsonCount(0, 'data');

        $bookmarks = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id]);

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $response = $this->getTestResponse()
            ->assertSuccessful()
            ->assertJsonCount(5, 'data')
            ->assertJson(function (AssertableJson $json) {
                $json->etc()
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) {
                        $json->where('attributes.is_user_favourite', true)->etc();
                    })
                    ->etc();
            })
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

        $this->assertBookmarkJson($response->json('data.0'));
    }
}
