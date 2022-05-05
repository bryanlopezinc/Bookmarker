<?php

namespace Tests\Feature;

use App\Models\Favourite;
use App\Models\UserResourcesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateFavouriteTest extends TestCase
{
    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createFavourite'), $parameters);
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

    public function testWillAddBookmarkToFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->withoutExceptionHandling()->getTestResponse(['bookmark' => $bookmark->id])->assertCreated();

        $this->assertDatabaseHas(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count'   => 1,
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ]);
    }

    public function testReturnErrorWhenBookmarkExistsInFavourites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['bookmark' => $bookmark->id])->assertCreated();
        $this->getTestResponse(['bookmark' => $bookmark->id])->assertStatus(Response::HTTP_CONFLICT);
    }

    public function testReturnErrorWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $this->getTestResponse(['bookmark' => $bookmark->id])->assertForbidden();
    }

    public function testWillNotAddInvalidBookmarkToFavourite(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['bookmark' => $bookmark->id + 1])->assertNotFound();

        $this->assertDatabaseMissing(Favourite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);
    }
}
