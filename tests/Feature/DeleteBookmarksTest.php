<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bookmark;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\WillCheckBookmarksHealth;

class DeleteBookmarksTest extends TestCase
{
    use WillCheckBookmarksHealth;

    protected function deleteBookmarksResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmark'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/bookmarks', 'deleteBookmark');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteBookmarksResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteBookmarksResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('ids');

        $this->deleteBookmarksResponse([
            'ids' => '1,1,3,4,5',
        ])->assertJsonValidationErrors([
            "ids.0" => ["The ids.0 field has a duplicate value."],
            "ids.1" => ["The ids.1 field has a duplicate value."]
        ]);

        $this->deleteBookmarksResponse(['ids' => collect()->times(51)->implode(',')])
            ->assertJsonValidationErrorFor('ids')
            ->assertJsonValidationErrors([
                'ids' => [
                    'cannot delete more than 50 bookmarks in one request'
                ]
            ]);
    }

    public function testDeleteBookmark(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->deleteBookmarksResponse(['ids' => (string)$bookmark->id])->assertOk();

        $this->assertDatabaseMissing(Bookmark::class, ['id' => $bookmark->id]);
    }

    public function testWilNotCheckBookmarksHealth(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmark = BookmarkFactory::new()->for($user)->create();

        $this->deleteBookmarksResponse(['ids' => (string)$bookmark->id])->assertOk();

        $this->assertBookmarksHealthWillNotBeChecked([$bookmark->id]);
    }

    public function testWillReturnNotFoundResponseWhenBookmarkDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $bookmarks = BookmarkFactory::times(3)->for($user)->create()->pluck('id');

        //Assert will return not found when one of the ids does not exits
        $this->deleteBookmarksResponse(['ids' => $bookmarks->add($bookmarks->last() + 1)->implode(',')])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);

        $this->deleteBookmarksResponse(['ids' => (string) ($bookmarks->last() + 1)])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotOwnBookmark(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->deleteBookmarksResponse(['ids' => (string)$model->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'BookmarkNotFound']);
    }
}
