<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Favourite;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
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
        $user = UserFactory::new()->count(2)->create();

        Passport::actingAs($loggedInUser = $user->first());

        $this->getTestResponse()->assertSuccessful()->assertJsonCount(0, 'data');

        BookmarkFactory::new()
            ->count(5)
            ->create(['user_id' => $loggedInUser->id])
            ->map(fn (Bookmark $model) => ['bookmark_id' => $model->id, 'user_id' => $loggedInUser->id])
            ->tap(fn (Collection $collection) => Favourite::insert($collection->all()));

        Favourite::query()->create([
            'bookmark_id' => BookmarkFactory::new()->create(['user_id' => $user->last()->id])->id,
            'user_id' => $user->last()->id
        ]);

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
