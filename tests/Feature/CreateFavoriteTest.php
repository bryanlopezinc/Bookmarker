<?php

namespace Tests\Feature;

use App\Models\Favorite;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
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
        $this->assertRouteIsAccessibleViaPath('v1/users/bookmarks/favorites', 'createFavorite');
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
                'bookmarks' => ['cannot add more than 50 bookmarks simultaneously']
            ]);

        $this->createFavoriteResponse(['bookmarks' => '1,1,3,4,5',])
            ->assertJsonValidationErrors([
                "bookmarks.0" => ["The bookmarks.0 field has a duplicate value."],
                "bookmarks.1" => ["The bookmarks.1 field has a duplicate value."]
            ]);

        $this->createFavoriteResponse(['bookmarks' => '1,2,-3,foo'])
            ->assertJsonValidationErrors(['bookmarks.2', 'bookmarks.3']);
    }

    public function testAddFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(2)->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $favorites = Favorite::query()->where('user_id', $user->id)->get();

        $this->assertCount(2, $favorites);
        $this->assertEquals(
            $bookmarks->pluck('id')->sort()->all(),
            $favorites->pluck('bookmark_id')->sort()->all()
        );
    }

    public function testWillCheckBookmarksHealth(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::new()->count(5)->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => $bookmarks->pluck('id')->implode(',')])->assertCreated();

        $this->assertBookmarksHealthWillBeChecked($bookmarks->pluck('id')->all());
    }

    public function testWillReturnConflictWhenBookmarkExistsInFavorites(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userBookmarksIds = BookmarkFactory::times(3)
            ->for($user)
            ->create()
            ->pluck('id')
            ->map(fn (int $id) => (string) $id);

        $this->createFavoriteResponse(['bookmarks' => $userBookmarksIds[0]])->assertCreated();

        $this->createFavoriteResponse(['bookmarks' => $userBookmarksIds->implode(',')])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'FavoritesAlreadyExists',
                'conflict' => [intval($userBookmarksIds[0])]
            ]);
    }

    public function testWhenBookmarkDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->create();

        $this->createFavoriteResponse(['bookmarks' => (string) $bookmark->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenBookmarksDoesNotExist(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->createFavoriteResponse(['bookmarks' => (string) ($bookmark->id + 1)])
            ->assertNotFound()
            ->assertExactJson(['message' => "BookmarkNotFound"]);

        $this->assertDatabaseMissing(Favorite::class, ['user_id' => $user->id]);
    }
}
