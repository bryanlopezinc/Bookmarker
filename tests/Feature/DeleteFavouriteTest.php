<?php

namespace Tests\Feature;

use App\Models\Favourite;
use App\Models\UserResourcesCount;
use App\Repositories\FavouritesRepository;
use App\ValueObjects\ResourceId;
use App\ValueObjects\UserId;
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

        $bookmark = BookmarkFactory::new()->create([]);

        (new FavouritesRepository)->create(new ResourceId($bookmark->id), UserId::fromAuthUser());

        $this->withoutExceptionHandling()->getTestResponse(['bookmark' => $bookmark->id])->assertNoContent();

        $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count'   => 0,
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ]);
    }

    public function testReturnErrorWhenUserDoesNotOwnFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        (new FavouritesRepository)->create(new ResourceId($bookmark->id), new UserId($user->id + 1));

        $this->getTestResponse(['bookmark' => $bookmark->id])->assertForbidden();
    }
}
