<?php

namespace Tests\Feature;

use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserFavouritesTest extends TestCase
{
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

        $this->getTestResponse(['page' => -1])->assertUnprocessable()->assertJsonValidationErrorFor('page');
        $this->getTestResponse(['page' => 2001])->assertUnprocessable()->assertJsonValidationErrorFor('page');
        $this->getTestResponse(['per_page' => 14])->assertUnprocessable()->assertJsonValidationErrorFor('per_page');
        $this->getTestResponse(['per_page' => 40])->assertUnprocessable()->assertJsonValidationErrorFor('per_page');
    }

    public function testWillFetchUserFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->getTestResponse()->assertSuccessful()->assertJsonCount(0, 'data');

        $bookmarks = BookmarkFactory::new()->count(5)->create(['user_id' => $user->id]);

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $this->getTestResponse()
            ->assertSuccessful()
            ->assertJsonCount(5, 'data')
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
