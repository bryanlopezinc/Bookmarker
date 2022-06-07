<?php

namespace Tests\Feature;

use App\Models\Favourite;
use App\Models\UserFavouritesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteFavouriteTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFavourite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/favourites', 'deleteFavourite');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('bookmark');
    }

    public function testWillDeleteFavourite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->getTestResponse(['bookmark' => $bookmark->id])->assertNoContent();

        $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserFavouritesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserFavouritesCount::TYPE
        ]);
    }

    public function testReturnErrorWhenUserDoesNotOwnFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->postJson(route('createFavourite'), ['bookmarks' => (string) $bookmark->id])->assertCreated();

        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse(['bookmark' => $bookmark->id])->assertForbidden();
    }
}
