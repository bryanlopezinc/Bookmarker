<?php

namespace Tests\Feature;

use App\Models\Favorite;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class CreateFavoriteTest extends TestCase
{
    use WillCheckBookmarksHealth;

    protected function createFavoriteResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(route('createFavorite'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/favorites', 'createFavorite');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->createFavoriteResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFavoriteResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('bookmarks');

        $this->createFavoriteResponse(['bookmarks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('bookmarks');

        $this->createFavoriteResponse(['bookmarks' => collect()->times(51)->implode(',')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'bookmarks' => [
                    'cannot add more than 50 bookmarks simultaneously'
                ]
            ]);

        $this->createFavoriteResponse([
            'bookmarks' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
            "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
        ]);
    }

    public function testAddFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->assertDatabaseHas(Favorite::class, [
            'bookmark_id' => $bookmark->id,
            'user_id' => $user->id
        ]);
    }

    public function testAddMultipleBookmarksToFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count($amount = 5)->for($user)->create()->pluck('id');

        $this->createFavoriteResponse(['bookmarks' => $bookmarks->implode(',')])->assertCreated();

        $favorites = Favorite::query()
            ->where('user_id', $user->id)
            ->get()
            ->tap(fn (Collection $collection) => $this->assertCount($amount, $collection))
            ->each(function (Favorite $favorite) use ($user, $bookmarks) {
                $this->assertTrue($bookmarks->contains($favorite->bookmark_id));
            });

        $this->assertCount($amount, $favorites);
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillReturnConflictResponseWhenBookmarkExistsInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])->assertCreated();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => "BookmarksAlreadyExists"]);
    }

    public function testWhenBookmarkDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillNotFoundWhenBookmarksDoesNotExist(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) ($bookmark->id + 1)])
            ->assertNotFound()
            ->assertExactJson([ 'message' => "BookmarkNotFound"]);

        $this->assertDatabaseMissing(Favorite::class, ['user_id' => $user->id]);
    }
}
