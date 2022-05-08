<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\BookmarkTag;
use App\Models\UserResourcesCount;
use Database\Factories\BookmarkFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteBookmarksTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteBookmark'), $parameters);
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrorFor('ids');
    }

    public function testWillDeleteBookmark(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create(['user_id' => $user->id]);

        UserResourcesCount::query()->create([
            'user_id' => $user->id,
            'count' => 1,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);

        BookmarkTag::insert([
            [
                'bookmark_id' => $model->id,
                'tag_id' => 20
            ],
            [
                'bookmark_id' => $model->id,
                'tag_id' => 30
            ]
        ]);

        $this->getTestResponse(['ids' => (string)$model->id])->assertStatus(204);

        $this->assertDatabaseMissing(Bookmark::class, ['id' => $model->id]);

        $this->assertDatabaseMissing(BookmarkTag::class, [
            'bookmark_id' => $model->id,
            'tag_id' => 20
        ]);

        $this->assertDatabaseMissing(BookmarkTag::class, [
            'bookmark_id' => $model->id,
            'tag_id' => 30
        ]);

        $this->assertDatabaseHas(UserResourcesCount::class, [
            'user_id' => $user->id,
            'count' => 0,
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ]);
    }

    public function testWillReturnSuccessResponseIfBookmarkDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = BookmarkFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['ids' => (string)($model->id + 1)])->assertStatus(204);
    }

    public function testWillReturnForbiddenWhenUserDoesNotOwnBookmark(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = BookmarkFactory::new()->create();

        $this->getTestResponse(['ids' => (string)$model->id])->assertForbidden();
    }
}
