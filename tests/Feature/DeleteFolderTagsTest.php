<?php

namespace Tests\Feature;

use App\Models\Taggable;
use Database\Factories\FolderFactory;
use Database\Factories\TagFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeleteFolderTagsTest extends TestCase
{
    use WithFaker;

    protected function getTestResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolderTags'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibeViaPath('v1/folders/tags/remove', 'deleteFolderTags');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->getTestResponse()->assertUnauthorized();
    }

    public function testWillThrowValidationWhenRequiredAttrbutesAreMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->getTestResponse()->assertJsonValidationErrors(['id', 'tags']);
    }

    public function testWillDeleteFolderTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = FolderFactory::new()->create(['user_id' => $user->id]);
        $tag = TagFactory::new()->create();

        Taggable::create($tagAttributes = [
            'taggable_id' => $model->id,
            'tag_id' => $tag->id,
            'tagged_by_id' => $user->id,
            'taggable_type' => Taggable::FOLDER_TYPE
        ]);

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $tag->name
        ])->assertOk();

        $this->assertDatabaseMissing(Taggable::class, $tagAttributes);
    }

    public function testWillReturnNotFoundResponseIfFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = FolderFactory::new()->create([
            'user_id' => $user->id
        ]);

        $this->getTestResponse(['id' => $model->id + 1, 'tags' => $this->faker->word])->assertNotFound();
    }

    public function testWillReturnSuccessIfFolderDoesNotHaveTags(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $model = FolderFactory::new()->create(['user_id' => $user->id]);

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $this->faker->word
        ])->assertOk();
    }

    public function testWillReturnForbiddenWhenUserDoesNotOwnFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $model = FolderFactory::new()->create();

        $this->getTestResponse([
            'id' => $model->id,
            'tags' => $this->faker->word
        ])->assertForbidden();
    }
}